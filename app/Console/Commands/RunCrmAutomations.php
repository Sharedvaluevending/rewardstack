<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\CrmAutomationRunner;
use Illuminate\Console\Command;

class RunCrmAutomations extends Command
{
    protected $signature = 'crm:run-automations {--business_id=}';
    protected $description = 'Run CRM automations (winback, punch nudges, expiring offers)';

    public function handle(CrmAutomationRunner $runner): int
    {
        $businessId = $this->option('business_id');

        $q = Business::query()->where('is_active', true);
        if ($businessId) {
            $q->where('id', (int) $businessId);
        }

        $count = 0;
        $queued = 0;

        $q->orderBy('id')->chunkById(50, function ($businesses) use ($runner, &$count, &$queued) {
            foreach ($businesses as $b) {
                $count++;
                $result = $runner->runForBusiness($b);
                foreach (($result['automations'] ?? []) as $r) {
                    $queued += (int) ($r['sent'] ?? 0);
                }
            }
        });

        $this->info("Processed {$count} business(es). Queued {$queued} email(s).");

        return self::SUCCESS;
    }
}

