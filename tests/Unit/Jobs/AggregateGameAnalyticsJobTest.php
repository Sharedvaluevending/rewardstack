<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Maintenance\AggregateGameAnalyticsJob;
use App\Models\Business;
use App\Models\GameAnalyticsDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateGameAnalyticsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_creates_analytics_records_for_businesses(): void
    {
        $business = Business::factory()->create();
        $before = GameAnalyticsDaily::count();

        $job = new AggregateGameAnalyticsJob(1, null);
        $job->handle();

        $this->assertGreaterThan($before, GameAnalyticsDaily::count());
        $record = GameAnalyticsDaily::where('business_id', $business->id)->first();
        $this->assertNotNull($record);
    }

    public function test_handle_with_business_id_only_processes_that_business(): void
    {
        $business = Business::factory()->create(['name' => 'Only Biz']);

        $job = new AggregateGameAnalyticsJob(1, $business->id);
        $job->handle();

        $count = GameAnalyticsDaily::where('business_id', $business->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
