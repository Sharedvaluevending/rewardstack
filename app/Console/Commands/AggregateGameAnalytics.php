<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\GameAnalyticsDaily;
use App\Jobs\Maintenance\AggregateGameAnalyticsJob;
use Illuminate\Console\Command;

class AggregateGameAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:aggregate {--days=30 : Number of days to aggregate} {--business_id= : Specific business ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate daily game analytics from GamePlay records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (filter_var(env('ANALYTICS_QUEUE_ENABLED', false), FILTER_VALIDATE_BOOL) && config('queue.default') !== "sync") {
            AggregateGameAnalyticsJob::dispatch((int) $this->option('days'), $this->option('business_id') ? (int) $this->option('business_id') : null);
            $this->info('Queued analytics aggregation job.');
            return 0;
        }

        $days = $this->option('days');
        $specificBusinessId = $this->option('business_id');

        $this->info("Aggregating game analytics for the last {$days} days...");

        // Get businesses to process
        $businesses = $specificBusinessId
            ? Business::where('id', $specificBusinessId)->get()
            : Business::all();

        $totalRecords = 0;

        foreach ($businesses as $business) {
            $this->info("Processing business: {$business->name} (ID: {$business->id})");

            for ($i = 0; $i < $days; $i++) {
                $date = now()->subDays($i)->toDateString();

                // Aggregate for all games (game_id = null)
                $record = GameAnalyticsDaily::recordDaily($business->id, null, $date);
                $totalRecords++;

                $this->line("  {$date}: {$record->total_plays} plays");
            }
        }

        $this->info("Completed! Created/updated {$totalRecords} analytics records.");
    }
}
