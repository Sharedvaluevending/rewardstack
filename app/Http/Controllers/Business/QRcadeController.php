<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Business;
use App\Models\BusinessGame;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\GamePack;
use App\Models\BusinessGamePack;
use App\Models\Leaderboard;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\GameAnalyticsDaily;
use App\Services\GameService;
use App\Services\PrizeService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class QRcadeController extends Controller
{
    protected GameService $gameService;
    protected PrizeService $prizeService;
    protected StripeService $stripeService;

    public function __construct(GameService $gameService, PrizeService $prizeService, StripeService $stripeService)
    {
        $this->gameService = $gameService;
        $this->prizeService = $prizeService;
        $this->stripeService = $stripeService;
    }

    /**
     * QRcade Dashboard
     */
    public function index(Request $request)
    {
        $business = $request->user()->business;
        
        $stats = $this->gameService->getBusinessGameStats($business->id);
        
        $recentPlays = GamePlay::where('business_id', $business->id)
            ->with(['user', 'game'])
            ->latest()
            ->limit(10)
            ->get();

        $topPlayers = GamePlay::where('business_id', $business->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('user_id, SUM(score) as total_score, COUNT(*) as games_played')
            ->groupBy('user_id')
            ->with('user')
            ->orderByDesc('total_score')
            ->limit(5)
            ->get();

        $enabledGames = BusinessGame::where('business_id', $business->id)
            ->where('is_enabled', true)
            ->with('game')
            ->get();

        return Inertia::render('Business/QRcade/Index', [
            'stats' => $stats,
            'recentPlays' => $recentPlays,
            'topPlayers' => $topPlayers,
            'enabledGames' => $enabledGames,
        ]);
    }

    /**
     * QRcade How-To (step-by-step guide)
     */
    public function howTo(Request $request)
    {
        $business = $request->user()->business;

        return Inertia::render('Business/QRcade/HowTo', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
        ]);
    }

    /**
     * Games Management
     */
    public function games(Request $request)
    {
        $business = $request->user()->business;

        // Get all available games (we'll show all, but mark which ones are accessible)
        $allGames = Game::active()
            ->orderBy('tier')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($game) use ($business) {
                // Determine if game is accessible based on subscription tier
                // Convert to array and add is_accessible property
                $gameArray = $game->toArray();
                $gameArray['is_accessible'] = $this->canAccessGame($business, $game);
                return $gameArray;
            })
            ->values()
            ->all();

        // Get business's enabled games
        $businessGames = BusinessGame::where('business_id', $business->id)
            ->with('game')
            ->get()
            ->keyBy('game_id');

        return Inertia::render('Business/QRcade/Games', [
            'allGames' => $allGames,
            'businessGames' => $businessGames,
            'gameTiers' => Game::tiers(),
            'subscriptionTier' => $business->subscription_tier ?? 'starter',
        ]);
    }

    /**
     * Check if business can access a game based on subscription tier
     */
    private function canAccessGame(\App\Models\Business $business, Game $game): bool
    {
        // DB-driven plan features are canonical via Business::canAccess()
        // Starter: basic only
        // Growth: basic + pro
        // Pro: basic + pro + premium
        // Enterprise: all tiers (including seasonal)

        // Basic games are always available
        if ($game->tier === Game::TIER_BASIC) {
            return true;
        }
        
        // Pro games require pro_games feature
        if ($game->tier === Game::TIER_PRO) {
            return $business->canAccess('pro_games');
        }
        
        // Premium games require premium_games feature
        if ($game->tier === Game::TIER_PREMIUM) {
            return $business->canAccess('premium_games');
        }
        
        // Seasonal games require seasonal_games feature
        if ($game->tier === Game::TIER_SEASONAL || $game->is_seasonal) {
            return $business->canAccess('seasonal_games');
        }
        
        return false;
    }

    /**
     * Toggle game enabled status
     */
    public function toggleGame(Request $request, Game $game)
    {
        $business = $request->user()->business;

        $businessGame = BusinessGame::firstOrCreate(
            ['business_id' => $business->id, 'game_id' => $game->id],
            ['is_enabled' => false]
        );

        $wasEnabled = $businessGame->is_enabled;
        $businessGame->update(['is_enabled' => !$businessGame->is_enabled]);

        // Auto-complete onboarding steps (only when enabling for the first time)
        if ($businessGame->is_enabled && !$wasEnabled) {
            $business->completeOnboardingStep('setup_basic_games');
            
            // Check if it's a pro game
            if ($game->tier === 'pro' && $business->canAccess('pro_games')) {
                $business->completeOnboardingStep('setup_pro_games');
            }
        }

        // NOTE: Inertia POSTs can sometimes arrive without a reliable Referer header,
        // which makes redirect()->back() land on a non-existent URL (causing a 404).
        // Always redirect to the games management page.
        return redirect()
            ->route('business.qrcade.games')
            ->with('success', $businessGame->is_enabled ? 'Game enabled' : 'Game disabled');
    }

    /**
     * Update game settings
     */
    public function updateGame(Request $request, Game $game)
    {
        $business = $request->user()->business;

        if (!$this->canAccessGame($business, $game)) {
            abort(403, 'Your plan does not include access to this game.');
        }

        $validated = $request->validate([
            'schedule' => 'nullable|array',
            'max_plays_per_day' => 'nullable|integer|min:1',
            'max_plays_per_week' => 'nullable|integer|min:1',
            'cooldown_minutes' => 'nullable|integer|min:0',
            'leaderboard_enabled' => 'boolean',
            'staff_can_play' => 'boolean',
        ]);

        BusinessGame::updateOrCreate(
            ['business_id' => $business->id, 'game_id' => $game->id],
            $validated
        );

        // See note in toggleGame(): avoid redirect()->back() for Inertia form submits.
        return redirect()
            ->route('business.qrcade.games')
            ->with('success', 'Game settings updated');
    }

    /**
     * Rewards Configuration
     */
    public function rewards(Request $request)
    {
        $business = $request->user()->business;

        $qrCodeGames = QRCodeGame::where('business_id', $business->id)
            ->with(['qrCode', 'game', 'promotion'])
            ->get();

        $leaderboards = Leaderboard::active()
            ->where('business_id', $business->id)
            ->whereIn('type', [Leaderboard::TYPE_GAME_SPECIFIC, Leaderboard::TYPE_LOCATION])
            ->with('promotion')
            ->get();
        $locationLeaderboard = $leaderboards->firstWhere('type', Leaderboard::TYPE_LOCATION);
        $gameSpecificLeaderboards = $leaderboards
            ->where('type', Leaderboard::TYPE_GAME_SPECIFIC)
            ->keyBy('game_id');

        // Calculate actual wins for each QR code game and sync if needed
        foreach ($qrCodeGames as $qrCodeGame) {
            $actualWins = $qrCodeGame->getActualTotalWinsAttribute();
            // If the stored value doesn't match actual, sync it
            if ($qrCodeGame->total_wins !== $actualWins) {
                $qrCodeGame->syncTotalWins();
                // Refresh the model to get updated value
                $qrCodeGame->refresh();
            }
        }

        foreach ($qrCodeGames as $qrCodeGame) {
            if (($qrCodeGame->qrCode->type ?? null) !== 'qrcade_leaderboard') {
                continue;
            }

            $leaderboard = $gameSpecificLeaderboards->get($qrCodeGame->game_id) ?? $locationLeaderboard;
            if (!$leaderboard) {
                continue;
            }

            $qrCodeGame->setAttribute('leaderboard_prize', [
                'leaderboard_id' => $leaderboard->id,
                'promotion' => $leaderboard->promotion,
                'prize_config' => $leaderboard->prize_config,
            ]);
        }

        $promotions = $business->promotions()
            ->where('is_active', true)
            ->get();

        $rewardStats = $this->prizeService->getRewardStats($business->id);

        return Inertia::render('Business/QRcade/Rewards', [
            'qrCodeGames' => $qrCodeGames,
            'promotions' => $promotions,
            'rewardStats' => $rewardStats,
            'winModes' => [
                'always' => 'Always Win',
                'skill' => 'Skill-Based',
                'random' => 'Random Chance',
                'tiered' => 'Tiered (Gold/Silver/Bronze)',
            ],
        ]);
    }

    /**
     * Update QR code game rewards
     */
    public function updateRewards(Request $request, QRCodeGame $qrCodeGame)
    {
        $qrCodeGame->loadMissing('qrCode');

        $business = $request->user()->business;
        abort_unless(
            $qrCodeGame->qrCode && $qrCodeGame->qrCode->business_id === $business->id,
            403,
            'Unauthorized'
        );

        $validated = $request->validate([
            'promotion_id' => 'nullable|exists:promotions,id',
            'win_mode' => 'required|in:score,time,random,always,leaderboard,tiered',
            'min_score' => 'nullable|integer|min:0',
            'max_time' => 'nullable|integer|min:1',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'leaderboard_position' => 'nullable|integer|min:1|max:100',
            'daily_limit' => 'nullable|integer|min:1',
            'total_limit' => 'nullable|integer|min:1',
            'tier_rewards' => 'nullable|array',
            'tier_rewards.gold' => 'nullable|exists:promotions,id',
            'tier_rewards.silver' => 'nullable|exists:promotions,id',
            'tier_rewards.bronze' => 'nullable|exists:promotions,id',
            'score_tiers' => 'nullable|array',
            'score_tiers.gold' => 'nullable|integer|min:0',
            'score_tiers.silver' => 'nullable|integer|min:0',
            'score_tiers.bronze' => 'nullable|integer|min:0',
        ]);

        $qrType = $qrCodeGame->qrCode->type ?? null;
        if ($qrType === 'qrcade_leaderboard' && $validated['win_mode'] !== 'leaderboard') {
            return back()->with('error', 'Leaderboard QR codes can only use Leaderboard win mode.');
        }
        if ($qrType === 'qrcade' && $validated['win_mode'] === 'leaderboard') {
            return back()->with('error', 'Play-to-win QR codes cannot use Leaderboard win mode.');
        }

        $businessId = $qrCodeGame->qrCode->business_id ?? null;
        if ($businessId) {
            $promoIds = [];
            if (!empty($validated['promotion_id'])) {
                $promoIds[] = (int) $validated['promotion_id'];
            }
            if (!empty($validated['tier_rewards']) && is_array($validated['tier_rewards'])) {
                foreach ($validated['tier_rewards'] as $tierId) {
                    if (!empty($tierId)) {
                        $promoIds[] = (int) $tierId;
                    }
                }
            }

            if (!empty($promoIds)) {
                $ownedCount = \App\Models\Promotion::query()
                    ->whereIn('id', array_unique($promoIds))
                    ->where('business_id', $businessId)
                    ->count();
                if ($ownedCount !== count(array_unique($promoIds))) {
                    return back()->with('error', 'Selected promotions must belong to this business.');
                }
            }
        }

        // Build prize config
        $prizeConfig = [
            'min_score' => $validated['min_score'] ?? 0,
            'max_time' => $validated['max_time'] ?? null,
            'win_probability' => $validated['win_probability'] ?? 100,
            'leaderboard_position' => $validated['leaderboard_position'] ?? null,
            'daily_limit' => $validated['daily_limit'] ?? null,
            'total_limit' => $validated['total_limit'] ?? null,
        ];

        // Tiered validation + persistence
        if ($validated['win_mode'] === 'tiered') {
            $tierRewards = $validated['tier_rewards'] ?? [];
            $scoreTiers = $validated['score_tiers'] ?? [];

            // Require all 3 promotions + thresholds for tiered
            foreach (['gold', 'silver', 'bronze'] as $tier) {
                if (empty($tierRewards[$tier]) || !isset($scoreTiers[$tier])) {
                    return back()->with('error', 'Tiered rewards require Gold, Silver, and Bronze promotions and score thresholds.');
                }
            }

            $gold = (int)$scoreTiers['gold'];
            $silver = (int)$scoreTiers['silver'];
            $bronze = (int)$scoreTiers['bronze'];
            if ($gold < $silver || $silver < $bronze) {
                return back()->with('error', 'Tier thresholds must be Gold ≥ Silver ≥ Bronze.');
            }

            $qrCodeGame->update([
                'win_mode' => $validated['win_mode'],
                'promotion_id' => null, // tiered uses per-tier promos
                'tier_rewards' => [
                    'gold' => (int)$tierRewards['gold'],
                    'silver' => (int)$tierRewards['silver'],
                    'bronze' => (int)$tierRewards['bronze'],
                ],
                'score_tiers' => [
                    'gold' => $gold,
                    'silver' => $silver,
                    'bronze' => $bronze,
                ],
                'prize_config' => $prizeConfig,
            ]);

            return back()->with('success', 'Tiered prize configuration updated!');
        }

        // Non-tiered modes: clear tier config to avoid confusion
        $qrCodeGame->update([
            'promotion_id' => $validated['promotion_id'],
            'win_mode' => $validated['win_mode'],
            'prize_config' => $prizeConfig,
            'tier_rewards' => null,
            'score_tiers' => null,
        ]);

        return back()->with('success', 'Prize configuration updated!');
    }

    /**
     * Schedule Management
     */
    public function schedule(Request $request)
    {
        $business = $request->user()->business;

        $businessGames = BusinessGame::where('business_id', $business->id)
            ->with('game')
            ->get();

        return Inertia::render('Business/QRcade/Schedule', [
            'businessGames' => $businessGames,
            'daysOfWeek' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
    }

    /**
     * Update game schedule
     */
    public function updateSchedule(Request $request, BusinessGame $businessGame)
    {
        $business = $request->user()->business;
        abort_unless($businessGame->business_id === $business->id, 403, 'Unauthorized');

        $request->validate([
            'schedule' => 'required|array',
        ]);

        $businessGame->update(['schedule' => $request->schedule]);

        return back()->with('success', 'Schedule updated');
    }

    /**
     * Leaderboards Management
     */
    public function leaderboards(Request $request)
    {
        $business = $request->user()->business;

        $leaderboards = Leaderboard::where('business_id', $business->id)
            ->with(['game', 'qrCodeGames.qrCode:id,name,code'])
            ->get();

        $games = BusinessGame::where('business_id', $business->id)
            ->where('is_enabled', true)
            ->with('game')
            ->get()
            ->pluck('game');

        // Eligible Promotion QR codes for leaderboard prizes (no games attached).
        // Keep in sync with editLeaderboard() so create flow can configure prize immediately.
        $qrCodes = $business->qrCodes()
            ->whereNotNull('promotion_id')
            ->whereNotIn('type', ['qrcade', 'qrcade_leaderboard'])
            ->where('intended_use', 'leaderboard_prize')
            ->whereDoesntHave('qrCodeGames', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('promotion', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['promotion:id,name,discount_type,discount_value,ends_at,is_active,rules'])
            ->get(['id', 'name', 'code', 'type', 'promotion_id', 'intended_use', 'design'])
            ->filter(fn ($qrCode) => $this->isManualLeaderboardPrizeQr($qrCode))
            ->map(function ($qrCode) {
                $promotion = $qrCode->promotion;

                return [
                    'id' => $qrCode->id,
                    'name' => $qrCode->name,
                    'code' => $qrCode->code,
                    'type' => $qrCode->type,
                    'promotion' => $promotion ? [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'discount_type' => $promotion->discount_type,
                        'discount_value' => $promotion->discount_value,
                        'ends_at' => $promotion->ends_at,
                        'is_active' => $promotion->is_active,
                        'rules' => $promotion->rules,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Business/QRcade/Leaderboards', [
            'leaderboards' => $leaderboards,
            'games' => $games,
            'qrCodes' => $qrCodes,
            'leaderboardTypes' => [
                'location' => 'This Location Only',
                'game_specific' => 'Per Game (This Location)',
            ],
            'resetFrequencies' => [
                'never' => 'Never (All-Time)',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
            ],
        ]);
    }

    /**
     * Create leaderboard
     */
    public function createLeaderboard(Request $request)
    {
        $business = $request->user()->business;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:location,game_specific',
            'game_id' => 'required_if:type,game_specific|nullable|exists:games,id',
            'reset_frequency' => 'required|in:never,daily,weekly,monthly',
            'is_active' => 'boolean',
            'prize_config' => 'nullable|array',
            'qr_code_id' => 'nullable|exists:qr_codes,id',
        ]);

        $validated['business_id'] = $business->id;
        $validated['slug'] = \Str::slug($validated['name']) . '-' . $business->id;

        // If QR code is selected, it MUST be a Promotion QR code (no games attached).
        if (!empty($validated['qr_code_id'])) {
            $qrCode = QRCode::with(['promotion', 'qrCodeGames' => function ($q) {
                $q->where('is_active', true);
            }])->find($validated['qr_code_id']);

            if (!$qrCode || $qrCode->business_id !== $business->id) {
                return back()->withErrors(['qr_code_id' => 'Invalid QR code selected.']);
            }

            // Block QRcade/game QR codes from being used as leaderboard prizes.
            if (in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true) || $qrCode->qrCodeGames->isNotEmpty()) {
                return back()->withErrors([
                    'qr_code_id' => 'Leaderboard prizes must use a Promotion QR code (not a QRcade/game QR code).',
                ]);
            }

            if (!$this->isManualLeaderboardPrizeQr($qrCode)) {
                return back()->withErrors([
                    'qr_code_id' => 'Select a Promotion QR code marked as Leaderboard Prize (internal).',
                ]);
            }

            if (!$qrCode->promotion_id || !$qrCode->promotion || !$qrCode->promotion->is_active) {
                return back()->withErrors(['qr_code_id' => 'Selected QR code must have an active promotion attached.']);
            }

            // Prevent mismatch: promotion must not expire before the first period ends.
            $promotion = $qrCode->promotion;
            if ($promotion->ends_at && $validated['reset_frequency'] !== 'never') {
                $firstPeriodEnd = match ($validated['reset_frequency']) {
                    'daily' => now()->endOfDay(),
                    'weekly' => now()->endOfWeek(),
                    'monthly' => now()->endOfMonth(),
                    default => null,
                };
                if ($firstPeriodEnd && $promotion->ends_at->lt($firstPeriodEnd)) {
                    return back()->withErrors([
                        'qr_code_id' => "The promotion \"{$promotion->name}\" expires on {$promotion->ends_at->format('M j, Y')} which is before the first {$validated['reset_frequency']} period ends. Extend the promotion or choose a shorter reset frequency.",
                    ]);
                }
            }

            $validated['promotion_id'] = $qrCode->promotion_id;
        } else {
            $validated['promotion_id'] = null;
        }

        $leaderboard = Leaderboard::create($validated);
        $leaderboard->syncPrizeToQRCodeGames();

        // Link any existing leaderboard QR code games for this business/game to this leaderboard.
        $linkQuery = QRCodeGame::where('business_id', $business->id)
            ->whereNull('leaderboard_id')
            ->where('win_mode', QRCodeGame::WIN_MODE_LEADERBOARD)
            ->whereHas('qrCode', fn ($q) => $q->where('type', 'qrcade_leaderboard'));

        if ($leaderboard->type === Leaderboard::TYPE_GAME_SPECIFIC && $leaderboard->game_id) {
            $linkQuery->where('game_id', $leaderboard->game_id);
        }

        $linkQuery->update(['leaderboard_id' => $leaderboard->id]);

        // Auto-complete onboarding step
        $business->completeOnboardingStep('setup_leaderboards');

        return back()->with('success', 'Leaderboard created');
    }

    /**
     * Show leaderboard rankings
     */
    public function showLeaderboard(Request $request, Leaderboard $leaderboard)
    {
        $business = $request->user()->business;

        // Ensure leaderboard belongs to business
        if ($leaderboard->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        // Get top entries
        $topEntries = $leaderboard->getTopEntries(50);

        // Get period info
        $periodKey = $leaderboard->getCurrentPeriodKey();

        // Get all entries for this period
        $allEntries = $leaderboard->entries()
            ->where('period_key', $periodKey)
            ->with('user')
            ->orderBy('rank')
            ->get();

        // Get promotions for prize selection
        $promotions = $business->promotions()
            ->where('is_active', true)
            ->get(['id', 'name', 'discount_type', 'discount_value']);

        return Inertia::render('Business/QRcade/LeaderboardShow', [
            'leaderboard' => $leaderboard->load(['game', 'business']),
            'topEntries' => $topEntries,
            'allEntries' => $allEntries,
            'promotions' => $promotions,
            'periodKey' => $periodKey,
            'periodInfo' => [
                'current_period' => $periodKey,
                'reset_frequency' => $leaderboard->reset_frequency,
                'period_start' => $leaderboard->current_period_start,
                'period_end' => $leaderboard->current_period_end,
            ],
        ]);
    }

    /**
     * Edit leaderboard (settings)
     */
    public function editLeaderboard(Request $request, Leaderboard $leaderboard)
    {
        $business = $request->user()->business;

        // Ensure leaderboard belongs to business
        if ($leaderboard->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $games = BusinessGame::where('business_id', $business->id)
            ->where('is_enabled', true)
            ->with('game')
            ->get()
            ->pluck('game');

        $promotions = $business->promotions()
            ->where('is_active', true)
            ->get(['id', 'name', 'discount_type', 'discount_value']);

        // Leaderboard prizes must use a dedicated Promotion QR code (NOT a QRcade/game QR code).
        // This prevents accidentally selecting a game QR code that can award instant-win rewards.
        $qrCodes = $business->qrCodes()
            ->whereNotNull('promotion_id')
            ->whereNotIn('type', ['qrcade', 'qrcade_leaderboard'])
            ->where('intended_use', 'leaderboard_prize')
            ->whereDoesntHave('qrCodeGames', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('promotion', function ($q) {
                $q->where('is_active', true);
            })
            ->with(['promotion:id,name,discount_type,discount_value,ends_at,is_active,rules'])
            ->get(['id', 'name', 'code', 'type', 'promotion_id', 'intended_use', 'design'])
            ->filter(fn ($qrCode) => $this->isManualLeaderboardPrizeQr($qrCode))
            ->map(function ($qrCode) {
                $promotion = $qrCode->promotion;

                return [
                    'id' => $qrCode->id,
                    'name' => $qrCode->name,
                    'code' => $qrCode->code,
                    'type' => $qrCode->type,
                    // keep shape-compatible with older frontend expectations
                    'qr_code_games' => [],
                    'promotion' => $promotion ? [
                        'id' => $promotion->id,
                        'name' => $promotion->name,
                        'discount_type' => $promotion->discount_type,
                        'discount_value' => $promotion->discount_value,
                        'ends_at' => $promotion->ends_at,
                        'is_active' => $promotion->is_active,
                        'rules' => $promotion->rules,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Business/QRcade/LeaderboardEdit', [
            'leaderboard' => $leaderboard->load(['game']),
            'games' => $games,
            'promotions' => $promotions,
            'qrCodes' => $qrCodes,
            'leaderboardTypes' => [
                'location' => 'This Location Only',
                'game_specific' => 'Per Game (This Location)',
            ],
            'resetFrequencies' => [
                'never' => 'Never (All-Time)',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
            ],
        ]);
    }

    /**
     * Update leaderboard
     */
    public function updateLeaderboard(Request $request, Leaderboard $leaderboard)
    {
        $business = $request->user()->business;

        // Ensure leaderboard belongs to business
        if ($leaderboard->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:location,game_specific',
            'game_id' => 'required_if:type,game_specific|nullable|exists:games,id',
            'reset_frequency' => 'required|in:never,daily,weekly,monthly',
            'is_active' => 'boolean',
            'prize_config' => 'nullable|array',
            'qr_code_id' => 'nullable|exists:qr_codes,id',
        ]);

        // If QR code is selected, it MUST be a Promotion QR code (no games attached).
        if (!empty($validated['qr_code_id'])) {
            $qrCode = QRCode::with(['promotion', 'qrCodeGames' => function ($q) {
                $q->where('is_active', true);
            }])->find($validated['qr_code_id']);

            if (!$qrCode || $qrCode->business_id !== $business->id) {
                return back()->withErrors(['qr_code_id' => 'Invalid QR code selected.']);
            }

            // Block QRcade/game QR codes from being used as leaderboard prizes.
            if (in_array($qrCode->type, ['qrcade', 'qrcade_leaderboard'], true) || $qrCode->qrCodeGames->isNotEmpty()) {
                return back()->withErrors([
                    'qr_code_id' => 'Leaderboard prizes must use a Promotion QR code (not a QRcade/game QR code).',
                ]);
            }

            if (!$this->isManualLeaderboardPrizeQr($qrCode)) {
                return back()->withErrors([
                    'qr_code_id' => 'Select a Promotion QR code marked as Leaderboard Prize (internal).',
                ]);
            }

            if (!$qrCode->promotion_id || !$qrCode->promotion || !$qrCode->promotion->is_active) {
                return back()->withErrors(['qr_code_id' => 'Selected QR code must have an active promotion attached.']);
            }

            // Prevent mismatch: promotion must not expire before the next period ends.
            $promotion = $qrCode->promotion;
            $resetFreq = $validated['reset_frequency'];
            if ($promotion->ends_at && $resetFreq !== 'never') {
                $nextPeriodEnd = match ($resetFreq) {
                    'daily' => now()->endOfDay(),
                    'weekly' => now()->endOfWeek(),
                    'monthly' => now()->endOfMonth(),
                    default => null,
                };
                if ($nextPeriodEnd && $promotion->ends_at->lt($nextPeriodEnd)) {
                    return back()->withErrors([
                        'qr_code_id' => "The promotion \"{$promotion->name}\" expires on {$promotion->ends_at->format('M j, Y')} which is before the current {$resetFreq} period ends. Extend the promotion or choose a shorter reset frequency.",
                    ]);
                }
            }

            $validated['promotion_id'] = $qrCode->promotion_id;
        } else {
            // No QR code selected, clear promotion_id
            $validated['promotion_id'] = null;
        }

        // Update slug if name changed
        if ($validated['name'] !== $leaderboard->name) {
            $validated['slug'] = \Str::slug($validated['name']) . '-' . $business->id;
        }

        $leaderboard->update($validated);
        $leaderboard->syncPrizeToQRCodeGames();

        return redirect()->route('business.qrcade.leaderboards.show', $leaderboard)
            ->with('success', 'Leaderboard updated successfully');
    }

    protected function isManualLeaderboardPrizeQr(QRCode $qrCode): bool
    {
        if (($qrCode->intended_use ?? null) !== QRCode::INTENDED_USE_LEADERBOARD_PRIZE) {
            return false;
        }

        $source = data_get($qrCode->design, 'leaderboard_prize_source');
        if ($source === 'manual') {
            return true;
        }
        if ($source === 'auto') {
            return false;
        }

        $autoCreated = (bool) data_get($qrCode->design, 'auto_created', false);
        if ($autoCreated) {
            return false;
        }

        $name = $qrCode->name ?? '';
        if (str_ends_with($name, ' (Promo)') || str_starts_with($name, 'Ambassador Reward - ')) {
            return false;
        }

        return true;
    }

    /**
     * Delete leaderboard
     */
    public function deleteLeaderboard(Request $request, Leaderboard $leaderboard)
    {
        $business = $request->user()->business;

        // Ensure leaderboard belongs to business
        if ($leaderboard->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $leaderboard->delete();

        return redirect()->route('business.qrcade.leaderboards')
            ->with('success', 'Leaderboard deleted successfully');
    }

    /**
     * Toggle leaderboard active status
     */
    public function toggleLeaderboard(Request $request, Leaderboard $leaderboard)
    {
        $business = $request->user()->business;

        // Ensure leaderboard belongs to business
        if ($leaderboard->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $leaderboard->update(['is_active' => !$leaderboard->is_active]);

        // Avoid redirect()->back() for Inertia requests (can produce a 404 if Referer is missing).
        return redirect()
            ->route('business.qrcade.leaderboards')
            ->with('success', $leaderboard->is_active ? 'Leaderboard activated' : 'Leaderboard paused');
    }

    /**
     * Analytics
     */
    public function analytics(Request $request)
    {
        $business = $request->user()->business;
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        // Generate daily stats from GamePlay data since GameAnalyticsDaily is not populated
        $dailyStats = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayPlays = GamePlay::where('business_id', $business->id)
                ->whereDate('created_at', $date)
                ->get();

            $dailyStats->push([
                'date' => $date,
                'total_plays' => $dayPlays->count(),
                'unique_players' => $dayPlays->pluck('user_id')->unique()->filter()->count(), // Only count non-null user_ids
                'total_score' => $dayPlays->sum('score'),
                'avg_score' => $dayPlays->count() > 0 ? round($dayPlays->avg('score'), 1) : 0,
                'high_score' => $dayPlays->max('score') ?? 0,
            ]);
        }

        $gameStats = GamePlay::where('business_id', $business->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('game_id, COUNT(*) as plays, AVG(score) as avg_score, MAX(score) as high_score')
            ->groupBy('game_id')
            ->with('game')
            ->get();

        // Get game plays and convert to Toronto timezone for hour extraction
        $peakHours = GamePlay::where('business_id', $business->id)
            ->where('created_at', '>=', $startDate)
            ->get()
            ->groupBy(function ($play) {
                return (int)Carbon::parse($play->created_at)->setTimezone('America/Toronto')->format('G');
            })
            ->map(function ($group, $hour) {
                return (object)[
                    'hour' => (int)$hour,
                    'plays' => $group->count(),
                ];
            })
            ->sortByDesc('plays')
            ->values();

        $tieredConfigured = QRCodeGame::where('business_id', $business->id)
            ->where('win_mode', 'tiered')
            ->exists();

        return Inertia::render('Business/QRcade/Analytics', [
            'dailyStats' => $dailyStats,
            'gameStats' => $gameStats,
            'peakHours' => $peakHours,
            'summary' => $this->gameService->getBusinessGameStats($business->id, $days),
            'rewardStats' => $this->prizeService->getRewardStats($business->id, $days),
            'tieredConfigured' => $tieredConfigured,
        ]);
    }

    /**
     * Purchase game pack
     */
    public function purchasePack(Request $request, GamePack $gamePack)
    {
        $business = $request->user()->business;
        $billingPeriod = $request->get('billing_period', 'monthly');

        // Check if already subscribed
        $existing = BusinessGamePack::where('business_id', $business->id)
            ->where('game_pack_id', $gamePack->id)
            ->active()
            ->exists();

        if ($existing) {
            return back()->with('error', 'Already subscribed to this pack');
        }

        // Check if Stripe is configured
        if (!$this->stripeService->isConfigured()) {
            return back()->with('error', 'Payment system is not configured. Please contact support.');
        }

        try {
            $session = $this->stripeService->createGamePackCheckoutSession(
                $business,
                $gamePack,
                $billingPeriod
            );

            if (!$session) {
                return back()->with('error', 'Failed to create checkout session. Please try again.');
            }

            return redirect($session->url);
        } catch (\Exception $e) {
            \Log::error('Game pack checkout error: ' . $e->getMessage());
            return back()->with('error', 'Failed to create checkout session: ' . $e->getMessage());
        }
    }
}

