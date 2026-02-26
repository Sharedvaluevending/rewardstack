<?php

namespace App\Console\Commands;

use App\Services\StripeConnectService;
use App\Jobs\Maintenance\ProcessReferralPayoutsJob;
use Illuminate\Console\Command;

class ProcessReferralPayouts extends Command
{
    protected $signature = 'referrals:process-payouts';
    protected $description = 'Process automatic Stripe Connect payouts for referrers';

    public function handle(StripeConnectService $stripeConnect)
    {
        if (filter_var(env('REFERRALS_PAYOUTS_QUEUE_ENABLED', false), FILTER_VALIDATE_BOOL) && config('queue.default') !== "sync") {
            ProcessReferralPayoutsJob::dispatch();
            $this->info('Queued referral payouts job.');
            return 0;
        }

        if (!$stripeConnect->isEnabled()) {
            $this->warn('Stripe Connect is not enabled. Skipping automatic payouts.');
            return 0;
        }

        $this->info('Processing automatic referral payouts...');

        $result = $stripeConnect->processAutomaticPayouts();

        $this->info($result['message']);

        if ($result['processed'] > 0) {
            $this->info("✅ Successfully processed {$result['processed']} payouts");
        }

        if (isset($result['failed']) && $result['failed'] > 0) {
            $this->error("❌ {$result['failed']} payouts failed");
        }

        return 0;
    }
}
