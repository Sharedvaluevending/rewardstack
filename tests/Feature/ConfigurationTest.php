<?php

namespace Tests\Feature;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    /** @test */
    public function critical_commands_are_scheduled()
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        // We expect these signatures to be present in the schedule
        $expectedCommands = [
            'analytics:aggregate',
            'promotions:reconcile-stats',
            'ai-insights:generate',
            'leaderboards:award-prizes',
            'referrals:approve-commissions',
            'referrals:process-payouts',
            'crm:run-automations',
        ];

        foreach ($expectedCommands as $cmd) {
            $exists = $events->contains(function ($event) use ($cmd) {
                return str_contains($event->command, $cmd);
            });
            
            $this->assertTrue($exists, "Command {$cmd} is not scheduled.");
        }
    }

    /** @test */
    public function environment_config_is_sane_for_testing()
    {
        // In our test environment, we expect these to be set a certain way
        // This confirms PHPUnit is picking up the correct .env or phpunit.xml settings
        
        $this->assertEquals('testing', Config::get('app.env'));
        // Correct config key for Laravel 7+ is mail.default, not mail.mailer
        $this->assertEquals('array', Config::get('mail.default')); 
        $this->assertEquals('sync', Config::get('queue.default')); // Testing uses sync
    }
}
