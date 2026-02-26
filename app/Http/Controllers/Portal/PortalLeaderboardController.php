<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PortalLeaderboardController extends Controller
{
    /**
     * List Leaderboards
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get global/public leaderboards (cache briefly + keep active() scope applied)
        $publicLeaderboards = Cache::remember('leaderboards:public', now()->addSeconds(60), function () {
            return Leaderboard::active()
                ->where(function ($q) {
                    $q->where('type', 'global')
                      ->orWhere('type', 'game_specific');
                })
                ->with(['game'])
                ->limit(10)
                ->get();
        });

        // Get user's CURRENT rankings (current period per leaderboard)
        $allMyEntries = LeaderboardEntry::where('user_id', $user->id)
            ->with('leaderboard')
            ->get();

        $myRankings = $allMyEntries
            ->filter(function ($entry) {
                $lb = $entry->leaderboard;
                if (!$lb) return false;
                return $entry->period_key === $lb->getCurrentPeriodKey();
            })
            ->sortBy('rank')
            ->values();

        return Inertia::render('Portal/Leaderboards', [
            'publicLeaderboards' => $publicLeaderboards,
            'myRankings' => $myRankings,
            'stats' => [
                'best_rank' => $myRankings->min('rank'),
                'total_leaderboards' => $myRankings->count(),
                // Count current #1 positions (not period-end prizes).
                'total_wins' => $myRankings->where('rank', 1)->count(),
            ],
        ]);
    }

    /**
     * Show Leaderboard Details
     */
    public function show(Request $request, Leaderboard $leaderboard)
    {
        $user = $request->user();

        // Get top entries
        $topEntries = $leaderboard->getTopEntries(50);

        // Get user's entry
        $myEntry = LeaderboardEntry::where('leaderboard_id', $leaderboard->id)
            ->where('user_id', $user->id)
            ->where('period_key', $leaderboard->getCurrentPeriodKey())
            ->first();

        // Get entries around user
        $nearbyEntries = null;
        if ($myEntry && $myEntry->rank > 10) {
            $nearbyEntries = LeaderboardEntry::where('leaderboard_id', $leaderboard->id)
                ->where('period_key', $leaderboard->getCurrentPeriodKey())
                ->whereBetween('rank', [$myEntry->rank - 3, $myEntry->rank + 3])
                ->with('user')
                ->orderBy('rank')
                ->get();
        }

        // Determine if the challenge is permanently over (promotion expired or non-resetting + period ended)
        $leaderboard->loadMissing('promotion');
        $promoEnded = $leaderboard->promotion
            && $leaderboard->promotion->ends_at
            && $leaderboard->promotion->ends_at->isPast();
        $neverResetEnded = $leaderboard->reset_frequency === Leaderboard::RESET_NEVER
            && $leaderboard->current_period_end
            && $leaderboard->current_period_end->isPast();
        $challengeOver = $promoEnded || $neverResetEnded || !$leaderboard->is_active;

        return Inertia::render('Portal/LeaderboardDetail', [
            'backUrl' => str_starts_with((string) $request->get('back', ''), '/') ? $request->get('back') : '/portal/leaderboards',
            'leaderboard' => $leaderboard->load(['business', 'game']),
            'topEntries' => $topEntries,
            'myEntry' => $myEntry,
            'nearbyEntries' => $nearbyEntries,
            'periodInfo' => [
                'current_period' => $leaderboard->getCurrentPeriodKey(),
                'reset_frequency' => $leaderboard->reset_frequency,
                'period_end' => $leaderboard->current_period_end,
                'challenge_over' => $challengeOver,
            ],
        ]);
    }
}

