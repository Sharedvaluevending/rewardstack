<?php

namespace App\Console\Commands;

use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\Promotion;
use App\Notifications\PortalLeaderboardPrize;
use App\Services\GameService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AwardLeaderboardPrizes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leaderboards:award-prizes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Award prizes to top players when leaderboard periods end';

    protected GameService $gameService;

    public function __construct(GameService $gameService)
    {
        parent::__construct();
        $this->gameService = $gameService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for leaderboards that need prize awards...');

        // Find leaderboards that need reset (period just ended) and have prizes configured.
        // Also include leaderboards with null current_period_end that need initialization.
        $leaderboards = Leaderboard::active()
            ->where(function ($query) {
                $query->whereNotNull('promotion_id')
                    ->orWhereNotNull('qr_code_id');
            })
            ->where(function ($query) {
                $query->where(function ($q) {
                    // Period has ended
                    $q->whereNotNull('current_period_end')
                        ->where('current_period_end', '<=', now());
                })->orWhere(function ($q) {
                    // Never initialized -- needs first reset
                    $q->whereNull('current_period_end')
                        ->where('reset_frequency', '!=', Leaderboard::RESET_NEVER);
                });
            })
            ->with(['promotion', 'qrCode' => function($q) {
                $q->with('promotion');
            }])
            ->get();

        if ($leaderboards->isEmpty()) {
            $this->info('No leaderboards need prize awards at this time.');
            return 0;
        }

        $awarded = 0;
        $skipped = 0;

        foreach ($leaderboards as $leaderboard) {
            try {
                // Wrap each leaderboard in a transaction with a row-level lock
                // to prevent duplicate prize awards from concurrent runs.
                DB::transaction(function () use ($leaderboard, &$awarded, &$skipped) {
                    // Re-fetch with lock to prevent concurrent processing
                    $locked = Leaderboard::where('id', $leaderboard->id)->lockForUpdate()->first();
                    if (!$locked) {
                        $skipped++;
                        return;
                    }

                    // Handle uninitialized leaderboards: just set the period and move on
                    if (!$locked->current_period_end) {
                        $locked->reset();
                        $this->info("Initialized period for {$locked->name}");
                        $skipped++;
                        return;
                    }

                    if ($locked->current_period_end->gt(now())) {
                        $skipped++;
                        return;
                    }

                    // Warn if multiple periods have been missed (scheduler was down)
                    $periodsOverdue = $this->countPeriodsOverdue($locked);
                    if ($periodsOverdue > 1) {
                        Log::warning("Leaderboard '{$locked->name}' is {$periodsOverdue} periods overdue – catching up one at a time", [
                            'leaderboard_id' => $locked->id,
                            'current_period_end' => $locked->current_period_end->toDateTimeString(),
                            'periods_overdue' => $periodsOverdue,
                        ]);
                        $this->warn("{$locked->name} is {$periodsOverdue} periods overdue – processing oldest first");
                    }

                    // Get the completed period key (before reset)
                    $completedPeriodKey = $this->getCompletedPeriodKey($locked);
                    
                    if (!$completedPeriodKey) {
                        $this->warn("Skipping {$locked->name}: Could not determine completed period");
                        $skipped++;
                        return;
                    }

                    // Load promotion
                    $promotion = $locked->promotion;
                    if (!$promotion && $locked->qrCode) {
                        $promotion = $locked->qrCode->promotion;
                    }

                    if (!$promotion) {
                        $this->warn("Skipping {$locked->name}: No promotion configured");
                        $skipped++;
                        return;
                    }

                    // Check if promotion is active and valid
                    if (!$promotion->is_active) {
                        $this->warn("Skipping {$locked->name}: Promotion '{$promotion->name}' is inactive");
                        $skipped++;
                        return;
                    }

                    if (!$promotion->isCurrentlyValid()) {
                        $this->warn("Skipping {$locked->name}: Promotion '{$promotion->name}' is expired or not yet started");
                        $skipped++;
                        return;
                    }

                    // Check if prizes already awarded for this period
                    $existingRewards = \App\Models\GameReward::whereHas('leaderboardEntry', function($q) use ($locked, $completedPeriodKey) {
                        $q->where('leaderboard_id', $locked->id)
                          ->where('period_key', $completedPeriodKey);
                    })->count();

                    if ($existingRewards > 0) {
                        $this->info("Skipping {$locked->name}: Prizes already awarded for period {$completedPeriodKey}");
                        $skipped++;
                        return;
                    }

                    // Determine how many winners (synced with leaderboard_position from game config)
                    $prizeConfig = $locked->prize_config ?? [];
                    $topPlayers = $prizeConfig['leaderboard_position'] ?? $prizeConfig['top_players'] ?? 3;

                    // Get top entries for completed period
                    $topEntries = LeaderboardEntry::where('leaderboard_id', $locked->id)
                        ->where('period_key', $completedPeriodKey)
                        ->orderBy('score', 'desc')
                        ->orderBy('created_at', 'asc')
                        ->limit($topPlayers)
                        ->with(['user', 'leaderboard'])
                        ->get();

                    if ($topEntries->isEmpty()) {
                        $this->info("Skipping {$locked->name}: No entries found for period {$completedPeriodKey}");
                        $skipped++;
                        return;
                    }

                    // Award prizes to each winner
                    foreach ($topEntries as $entry) {
                        if (!$entry->user) {
                            $this->warn("Skipping entry {$entry->id}: No user associated");
                            continue;
                        }

                        $reward = $this->gameService->createLeaderboardPrizeReward($entry, $promotion);

                        if ($reward) {
                            $awarded++;
                            if (in_array($entry->user->role, ['user', 'customer'], true)) {
                                $entry->user->notify(new PortalLeaderboardPrize($locked, $reward, $entry));
                            }
                            $this->info("Awarded prize to {$entry->user->name} (Rank #{$entry->rank}) for {$locked->name}");
                            Log::info('Leaderboard prize awarded', [
                                'leaderboard_id' => $locked->id,
                                'leaderboard_name' => $locked->name,
                                'user_id' => $entry->user_id,
                                'entry_id' => $entry->id,
                                'period_key' => $completedPeriodKey,
                                'rank' => $entry->rank,
                                'promotion_id' => $promotion->id,
                                'reward_id' => $reward->id,
                            ]);
                        } else {
                            $this->warn("Could not award prize to {$entry->user->name} (Rank #{$entry->rank}): Limit reached or other issue");
                            Log::info('Leaderboard prize not awarded (limit reached)', [
                                'leaderboard_id' => $locked->id,
                                'user_id' => $entry->user_id,
                                'entry_id' => $entry->id,
                                'period_key' => $completedPeriodKey,
                                'rank' => $entry->rank,
                                'promotion_id' => $promotion->id,
                            ]);
                        }
                    }

                    // Reset the leaderboard after awarding prizes
                    $locked->reset();
                });

            } catch (\Exception $e) {
                $this->error("Error processing {$leaderboard->name}: " . $e->getMessage());
                Log::error('Error awarding leaderboard prizes', [
                    'leaderboard_id' => $leaderboard->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $skipped++;
            }
        }

        $this->info("Completed: {$awarded} prizes awarded, {$skipped} leaderboards skipped");
        return 0;
    }

    /**
     * Count how many periods have elapsed since the leaderboard's current_period_end.
     */
    protected function countPeriodsOverdue(Leaderboard $leaderboard): int
    {
        if (!$leaderboard->current_period_end) {
            return 0;
        }

        $end = $leaderboard->current_period_end;
        $now = now();

        return match ($leaderboard->reset_frequency) {
            Leaderboard::RESET_DAILY => (int) $end->diffInDays($now),
            Leaderboard::RESET_WEEKLY => (int) $end->diffInWeeks($now),
            Leaderboard::RESET_MONTHLY => (int) $end->diffInMonths($now),
            default => 0,
        };
    }

    /**
     * Get the completed period key for a leaderboard that needs reset
     */
    protected function getCompletedPeriodKey(Leaderboard $leaderboard): ?string
    {
        if (!$leaderboard->current_period_end) {
            return null;
        }

        // Calculate the period key for the period that just ended
        $periodEnd = \Carbon\Carbon::parse($leaderboard->current_period_end);

        return match ($leaderboard->reset_frequency) {
            Leaderboard::RESET_DAILY => $periodEnd->format('Y-m-d'),
            Leaderboard::RESET_WEEKLY => $periodEnd->format('o-\WW'),
            Leaderboard::RESET_MONTHLY => $periodEnd->format('Y-m'),
            default => null,
        };
    }
}
