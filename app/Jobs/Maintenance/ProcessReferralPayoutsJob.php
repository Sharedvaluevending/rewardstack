<?php

namespace App\Jobs\Maintenance;

use App\Services\StripeConnectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReferralPayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct()
    {
        $this->onQueue(env('QUEUE_HIGH', 'high'));
    }

    public function handle(StripeConnectService $stripeConnect): void
    {
        if (!$stripeConnect->isEnabled()) {
            return;
        }

        $result = $stripeConnect->processAutomaticPayouts();
        Log::info('Referral payouts processed', $result);
    }
}
