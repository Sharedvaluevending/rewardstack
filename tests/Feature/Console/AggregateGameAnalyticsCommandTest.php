<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\GameAnalyticsDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateGameAnalyticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_aggregate_sync_creates_records_for_businesses(): void
    {
        config(['queue.default' => 'sync']);
        putenv('ANALYTICS_QUEUE_ENABLED=false');

        $business = Business::factory()->create();
        $before = GameAnalyticsDaily::count();

        $this->artisan('analytics:aggregate', ['--days' => 1])
            ->expectsOutputToContain('Aggregating game analytics')
            ->expectsOutputToContain('Completed!')
            ->assertExitCode(0);

        $this->assertGreaterThan($before, GameAnalyticsDaily::count(), 'Command should create at least one analytics record');
        $record = GameAnalyticsDaily::where('business_id', $business->id)->first();
        $this->assertNotNull($record);
    }

    public function test_analytics_aggregate_with_business_id_only_processes_that_business(): void
    {
        config(['queue.default' => 'sync']);
        putenv('ANALYTICS_QUEUE_ENABLED=false');

        $business = Business::factory()->create(['name' => 'Target Biz']);

        $this->artisan('analytics:aggregate', ['--days' => 1, '--business_id' => $business->id])
            ->expectsOutputToContain('Target Biz')
            ->assertExitCode(0);

        $records = GameAnalyticsDaily::where('business_id', $business->id)->get();
        $this->assertGreaterThanOrEqual(1, $records->count(), 'Should create at least one record for the specified business');
    }

    public function test_analytics_aggregate_queues_job_when_queue_enabled(): void
    {
        config(['queue.default' => 'redis']);
        putenv('ANALYTICS_QUEUE_ENABLED=true');

        \Illuminate\Support\Facades\Queue::fake();

        $this->artisan('analytics:aggregate', ['--days' => 1])
            ->expectsOutputToContain('Queued analytics aggregation job.')
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Maintenance\AggregateGameAnalyticsJob::class);
    }
}
