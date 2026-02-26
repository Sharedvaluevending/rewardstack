<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Aggregate game analytics daily at 2 AM
        $schedule->command('analytics:aggregate --days=2')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // Reconcile promotion statistics daily at 3 AM (after analytics aggregation)
        $schedule->command('promotions:reconcile-stats')
            ->dailyAt('03:00')
            ->withoutOverlapping();

        // Generate AI insights weekly (Monday at 8 AM ET) — dispatches queue jobs per business
        $schedule->command('ai-insights:generate --period=7 --type=both')
            ->weeklyOn(1, '08:00')
            ->withoutOverlapping()
            ->name('generate-weekly-ai-insights');

        // Award leaderboard prizes hourly (checks for ended periods)
        $schedule->command('leaderboards:award-prizes')
            ->hourly()
            ->withoutOverlapping();

        // Notify users of expiring rewards daily
        $schedule->command('rewards:notify-expiring --days=3')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->name('notify-expiring-rewards');

        // -----------------------------------------------------------
        // REFERRAL AUTOMATION
        // -----------------------------------------------------------
        
        // 1. Approve commissions older than 30 days (Daily at 4 AM)
        $schedule->command('referrals:approve-commissions')
            ->dailyAt('04:00')
            ->withoutOverlapping();

        // 2. Process payouts for approved commissions > $25 (Daily at 5 AM)
        $schedule->command('referrals:process-payouts')
            ->dailyAt('05:00')
            ->withoutOverlapping();

        // -----------------------------------------------------------
        // STRIPE RECONCILIATION
        // -----------------------------------------------------------

        // Daily reconciliation of local subscription status vs Stripe
        // Catches missed webhooks, network failures, and drift
        $schedule->command('stripe:reconcile-subscriptions')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->name('reconcile-stripe-subscriptions');

        // -----------------------------------------------------------
        // BUSINESS HEALTH & FOLLOW-UPS
        // -----------------------------------------------------------
        $schedule->command('business-health:calculate')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->name('calculate-business-health');

        $schedule->command('business-health:create-follow-ups')
            ->dailyAt('06:05')
            ->withoutOverlapping()
            ->name('create-follow-up-tasks');

        // -----------------------------------------------------------
        // PRINTFUL ORDER SYNC (safety net for missed webhooks)
        // -----------------------------------------------------------
        $schedule->command('printful:sync-orders')
            ->everyFourHours()
            ->withoutOverlapping()
            ->name('sync-printful-orders');

        // -----------------------------------------------------------
        // CRM AUTOMATIONS (WINBACK / PUNCH NUDGES / EXPIRING OFFERS)
        // -----------------------------------------------------------
        $schedule->command('crm:run-automations')
            ->dailyAt('06:15')
            ->withoutOverlapping()
            ->name('crm-automations');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
