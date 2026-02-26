<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessFollowUp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateFollowUpTasks extends Command
{
    protected $signature = 'business-health:create-follow-ups';
    protected $description = 'Auto-create follow-up tasks for businesses at key milestones';

    private const MILESTONES = [
        3 => 'day_3_checkin',
        7 => 'week_1_review',
        28 => 'week_4_testimonial',
    ];

    public function handle(): int
    {
        $created = 0;

        foreach (self::MILESTONES as $days => $type) {
            $targetDate = today()->subDays($days);

            $businesses = Business::where('is_active', true)
                ->whereDate('created_at', $targetDate)
                ->whereDoesntHave('followUps', fn ($q) => $q->where('follow_up_type', $type))
                ->get();

            foreach ($businesses as $business) {
                BusinessFollowUp::create([
                    'business_id' => $business->id,
                    'follow_up_type' => $type,
                    'due_date' => today(),
                    'status' => 'pending',
                ]);
                $created++;
                $this->info("  Created {$type} for {$business->name}");
            }
        }

        $this->info("Done. {$created} follow-up(s) created.");
        Log::info('business-health:create-follow-ups completed', ['created' => $created]);

        return Command::SUCCESS;
    }
}
