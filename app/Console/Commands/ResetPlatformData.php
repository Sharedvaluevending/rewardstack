<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\QRCode;
use App\Models\Business;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;

class ResetPlatformData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:reset {--force : Force the operation to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely clear all testing data (scans, redemptions, promos, codes) while preserving accounts, the onboarding flyer QR, and the business card QR.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('This will DELETE all scans, redemptions, rewards, promotions, and QR codes (except the onboarding flyer QR + business card QR and their scans). User accounts will be reset but NOT deleted. Are you sure?')) {
            $this->info('Reset cancelled.');
            return;
        }

        $this->info('Starting platform reset...');

        $preserveQrIds = [];
        if (Schema::hasTable('qr_codes')) {
            $preserveQrIds = QRCode::withTrashed()
                ->whereIn('code', [OnboardingQr::CODE, BusinessCardQr::CODE])
                ->pluck('id')
                ->all();
        }

        // Tables to truncate completely
        $activityTables = [
            'redemptions',
            'game_plays',
            'game_sessions',
            'game_rewards',
            'punch_cards',
            'user_promo_tokens',
            'saved_qr_codes',
            'user_badges',
            'leaderboard_entries',
            'game_analytics_daily',
            'analytics_daily',
            'ai_insights',
            'merch_referral_awards',
            'merch_referral_rewards',
            'user_merch_unlocks',
            'merch_tags',
            'failed_jobs',
        ];

        $contentTables = [
            'promotions',
            'qr_code_games',
            'stackable_entries',
            'stackable_pools',
            'cross_promotions',
        ];

        $allTables = array_merge($activityTables, $contentTables);

        Schema::disableForeignKeyConstraints();

        foreach ($allTables as $table) {
            if (Schema::hasTable($table)) {
                $this->comment("Truncating table: {$table}");
                DB::table($table)->truncate();
            }
        }

        // Preserve onboarding + business card QR scans while clearing other scan data
        if (Schema::hasTable('scans')) {
            $this->comment('Clearing scans (preserving onboarding flyer QR & business card QR)');
            if (count($preserveQrIds)) {
                DB::table('scans')->whereNotIn('qr_code_id', $preserveQrIds)->delete();
            } else {
                DB::table('scans')->truncate();
            }
        }

        // Preserve the onboarding + business card QR code records
        if (Schema::hasTable('qr_codes')) {
            $this->comment('Clearing QR codes (preserving onboarding flyer QR & business card QR)');
            if (count($preserveQrIds)) {
                DB::table('qr_codes')->whereNotIn('id', $preserveQrIds)->delete();
            } else {
                DB::table('qr_codes')->truncate();
            }
        }

        $this->info('Resetting user stats...');
        
        // Reset user stats but keep the accounts
        DB::table('users')->update([
            'level' => 1,
            'xp' => 0,
            'lifetime_score' => 0,
            'highest_score' => 0,
            'total_games_played' => 0,
            'total_wins' => 0,
            'current_streak' => 0,
            'best_streak' => 0,
            'total_rewards_won' => 0,
            'total_rewards_redeemed' => 0,
            'total_savings' => 0,
            'badge_points' => 0,
            'total_badges' => 0,
        ]);

        Schema::enableForeignKeyConstraints();

        // Ensure system QR codes exist after reset (stable URLs + analytics continuity)
        if (Schema::hasTable('qr_codes')) {
            $business = Business::query()->orderBy('id')->first();
            if ($business) {
                // Onboarding flyer QR
                QRCode::withTrashed()->updateOrCreate(
                    ['code' => OnboardingQr::CODE],
                    [
                        'business_id' => $business->id,
                        'name' => OnboardingQr::NAME,
                        'type' => 'static',
                        'intended_use' => QRCode::INTENDED_USE_PUBLIC,
                        'destination_url' => OnboardingQr::destinationUrl(),
                        'design' => ['error_correction' => 'H'],
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );

                // Business card QR
                QRCode::withTrashed()->updateOrCreate(
                    ['code' => BusinessCardQr::CODE],
                    [
                        'business_id' => $business->id,
                        'name' => BusinessCardQr::NAME,
                        'type' => 'static',
                        'intended_use' => QRCode::INTENDED_USE_PUBLIC,
                        'destination_url' => BusinessCardQr::destinationUrl(),
                        'design' => ['error_correction' => 'H'],
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );
            }
        }

        $this->info('Platform reset successfully! You now have a clean slate.');
    }
}
