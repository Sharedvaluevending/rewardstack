<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReferralCommission;
use Illuminate\Support\Facades\Log;

class ApproveReferralCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referrals:approve-commissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-approve pending commissions older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for pending commissions to approve...');

        // 30-day holding period to cover refunds/disputes
        // We use created_at to determine age
        $count = ReferralCommission::where('status', ReferralCommission::STATUS_PENDING)
            ->where('created_at', '<=', now()->subDays(30))
            ->update(['status' => ReferralCommission::STATUS_APPROVED]);

        if ($count > 0) {
            $msg = "✅ Auto-approved {$count} commissions.";
            $this->info($msg);
            Log::info($msg);
        } else {
            $this->info('No commissions ready for approval.');
        }

        return 0;
    }
}
