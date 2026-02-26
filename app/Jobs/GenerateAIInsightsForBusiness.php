<?php

namespace App\Jobs;

use App\Http\Controllers\Business\AIInsightsController;
use App\Models\Business;
use App\Models\WeeklyAIInsight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateAIInsightsForBusiness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $businessId,
        public string $type,
        public int $periodDays
    ) {
    }

    /**
     * Prevent two jobs for the same business+type from running simultaneously.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("ai-insight:{$this->businessId}:{$this->type}"))
                ->releaseAfter(600)
                ->expireAfter(900),
        ];
    }

    public function handle(AIInsightsController $controller): void
    {
        $business = Business::find($this->businessId);
        if (!$business || !$business->is_active) {
            return;
        }

        $period = max(1, (int) $this->periodDays);
        $type = in_array($this->type, ['basic', 'advanced'], true) ? $this->type : 'basic';

        $cacheKey = 'ai-insights:' . $type . ':' . $business->id . ':' . $period;
        $statusKey = $cacheKey . ':status';
        $errorKey = $cacheKey . ':error';

        Cache::put($statusKey, [
            'state' => 'running',
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(30));
        Cache::forget($errorKey);

        $startDate = Carbon::now()->subDays($period)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        try {
            $data = $type === 'advanced'
                ? $controller->generateAdvancedInsights($business, $startDate, $endDate, (string) $period)
                : $controller->generateBasicInsights($business, $startDate, $endDate, (string) $period);

            Cache::put($cacheKey, $data, now()->addDays(8));
            WeeklyAIInsight::store($business->id, $type, $period, $data);

            Cache::put($statusKey, [
                'state' => 'ok',
                'finished_at' => now()->toISOString(),
            ], now()->addDays(8));
            Cache::forget($errorKey);

            Log::info("AI insights generated", [
                'business_id' => $business->id,
                'type' => $type,
                'period' => $period,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateAIInsightsForBusiness failed', [
                'business_id' => $business->id,
                'type' => $type,
                'period' => $period,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            Cache::put($statusKey, [
                'state' => 'failed',
                'failed_at' => now()->toISOString(),
                'attempt' => $this->attempts(),
            ], now()->addMinutes(30));
            Cache::put($errorKey, 'Generation failed (attempt ' . $this->attempts() . '). Will retry automatically.', now()->addMinutes(30));

            throw $e;
        }
    }
}
