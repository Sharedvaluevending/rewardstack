<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Jobs\GenerateAIInsightsForBusiness;
use App\Services\DeepSeekAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateAIInsights extends Command
{
    protected $signature = 'ai-insights:generate
        {--business-id= : Generate for specific business only}
        {--period=7 : Period in days}
        {--type=both : Type: basic, advanced, or both}
        {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Generate AI insights for all businesses (dispatches queue jobs for scalability)';

    public function handle(): int
    {
        $aiService = app(DeepSeekAIService::class);

        if (!$aiService->isConfigured()) {
            $this->error('DeepSeek API is not configured. Skipping AI insights generation.');
            return 1;
        }

        $businessId = $this->option('business-id');
        $period = (int) $this->option('period');
        $type = $this->option('type');
        $sync = $this->option('sync');

        if ($businessId) {
            $business = Business::find($businessId);
            if (!$business) {
                $this->error("Business with ID {$businessId} not found.");
                return 1;
            }
            $this->dispatchForBusiness($business, $type, $period, $sync, 0);
            $this->info('Dispatched insights generation for business ' . $businessId);
            return 0;
        }

        $businesses = Business::where('is_active', true)
            ->whereHas('owner', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        $count = $businesses->count();
        $this->info("Dispatching AI insights generation for {$count} businesses...");

        // Stagger jobs: 15-second gap between businesses to avoid API rate limits.
        // With 1000 businesses this spreads over ~4 hours per type, well within a weekly window.
        $delaySeconds = 0;
        $staggerGap = $count > 50 ? 15 : ($count > 10 ? 10 : 5);

        foreach ($businesses as $business) {
            $this->dispatchForBusiness($business, $type, $period, $sync, $delaySeconds);
            $delaySeconds += $staggerGap;
        }

        $totalMinutes = round($delaySeconds / 60, 1);
        $this->info("Dispatched {$count} businesses. Estimated completion in ~{$totalMinutes} minutes.");
        Log::info('AI insights generation dispatched', [
            'businesses' => $count,
            'type' => $type,
            'period' => $period,
            'stagger_gap' => $staggerGap,
            'estimated_minutes' => $totalMinutes,
        ]);

        return 0;
    }

    protected function dispatchForBusiness(Business $business, string $type, int $period, bool $sync, int $delaySeconds): void
    {
        $types = ($type === 'both') ? ['basic', 'advanced'] : [$type];

        foreach ($types as $t) {
            $job = new GenerateAIInsightsForBusiness($business->id, $t, $period);
            $job->onQueue('ai-insights');

            if ($sync) {
                $this->info("Generating {$t} insights for business {$business->id} (sync)...");
                dispatch_sync($job);
                $this->info("Done: {$t} insights for business {$business->id}");
            } else {
                if ($delaySeconds > 0) {
                    $job->delay(now()->addSeconds($delaySeconds));
                    // Stagger advanced 30s after basic for same business
                    if ($t === 'advanced') {
                        $job->delay(now()->addSeconds($delaySeconds + 30));
                    }
                }
                dispatch($job);
            }
        }
    }
}
