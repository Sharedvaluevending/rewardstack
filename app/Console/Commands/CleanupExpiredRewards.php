<?php

namespace App\Console\Commands;

use App\Models\GameReward;
use Illuminate\Console\Command;

class CleanupExpiredRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rewards:cleanup-expired {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired rewards that have no code or QR image (dead rewards from cleanup)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        // Mark AVAILABLE/CLAIMED rewards as EXPIRED when expires_at or valid_until has passed.
        $marked = GameReward::whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNotNull('expires_at')->where('expires_at', '<', now());
                })->orWhere(function ($qq) {
                    $qq->whereNotNull('valid_until')->where('valid_until', '<', now());
                });
            })
            ->update(['status' => GameReward::STATUS_EXPIRED]);

        if ($marked > 0) {
            $this->info("Marked {$marked} reward(s) as expired.");
        }

        // Find expired rewards that are missing codes or QR images
        $expiredRewards = GameReward::where('status', GameReward::STATUS_EXPIRED)
            ->where(function ($query) {
                $query->whereNull('reward_code')
                    ->orWhere('reward_code', '')
                    ->orWhereNull('qr_image_path');
            })
            ->get();

        if ($expiredRewards->isEmpty()) {
            $this->info('No expired rewards found that need cleanup.');
            return 0;
        }

        $this->info("Found {$expiredRewards->count()} expired reward(s) without codes or QR images:");

        foreach ($expiredRewards as $reward) {
            $this->line("  - Reward ID: {$reward->id}, User: {$reward->user_id}, Promotion: {$reward->promotion_id}, Code: " . ($reward->reward_code ?: 'MISSING') . ", QR: " . ($reward->qr_image_path ?: 'MISSING'));
        }

        if ($dryRun) {
            $this->warn('DRY RUN: No rewards were deleted. Remove --dry-run to actually delete them.');
            return 0;
        }

        if ($this->confirm('Do you want to delete these expired rewards?', true)) {
            $deleted = GameReward::where('status', GameReward::STATUS_EXPIRED)
                ->where(function ($query) {
                    $query->whereNull('reward_code')
                        ->orWhere('reward_code', '')
                        ->orWhereNull('qr_image_path');
                })
                ->delete();

            $this->info("Successfully deleted {$deleted} expired reward(s).");
            return 0;
        }

        $this->info('Cleanup cancelled.');
        return 0;
    }
}
