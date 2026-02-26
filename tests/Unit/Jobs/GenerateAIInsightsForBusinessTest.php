<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateAIInsightsForBusiness;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class GenerateAIInsightsForBusinessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_early_when_business_not_found(): void
    {
        $job = new GenerateAIInsightsForBusiness(99999, 'basic', 30);

        $controller = Mockery::mock(\App\Http\Controllers\Business\AIInsightsController::class);
        $controller->shouldNotReceive('generateBasicInsights');
        $controller->shouldNotReceive('generateAdvancedInsights');

        $job->handle($controller);

        $this->assertNull(Business::find(99999));
    }

    public function test_handle_sets_cache_status_when_business_exists(): void
    {
        $business = Business::factory()->create();
        $job = new GenerateAIInsightsForBusiness($business->id, 'basic', 7);

        $controller = Mockery::mock(\App\Http\Controllers\Business\AIInsightsController::class);
        $controller->shouldReceive('generateBasicInsights')
            ->once()
            ->andReturn(['summary' => 'test']);

        $job->handle($controller);

        $cacheKey = 'ai-insights:basic:' . $business->id . ':7';
        $this->assertNotNull(Cache::get($cacheKey));
        $statusKey = $cacheKey . ':status';
        $status = Cache::get($statusKey);
        $this->assertSame('ok', $status['state'] ?? null);
    }
}
