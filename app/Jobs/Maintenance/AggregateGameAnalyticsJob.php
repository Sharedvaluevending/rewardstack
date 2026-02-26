<?php

namespace App\Jobs\Maintenance;

use App\Models\Business;
use App\Models\GameAnalyticsDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateGameAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $days = 30, public ?int $businessId = null)
    {
        $this->onQueue(env('QUEUE_LOW', 'low'));
    }

    public function handle(): void
    {
        $businesses = $this->businessId
            ? Business::where('id', $this->businessId)->get()
            : Business::all();

        foreach ($businesses as $business) {
            for ($i = 0; $i < $this->days; $i++) {
                $date = now()->subDays($i)->toDateString();
                GameAnalyticsDaily::recordDaily($business->id, null, $date);
            }
        }
    }
}
