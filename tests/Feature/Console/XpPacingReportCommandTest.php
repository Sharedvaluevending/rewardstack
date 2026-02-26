<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XpPacingReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_prints_report_and_exits_success(): void
    {
        $this->artisan('xp:pacing')
            ->expectsOutputToContain('XP / Level Pacing Report')
            ->expectsOutputToContain('Level curve:')
            ->expectsOutputToContain('Key thresholds:')
            ->expectsOutputToContain('Scenario estimate')
            ->assertExitCode(0);
    }

    public function test_command_accepts_options(): void
    {
        $this->artisan('xp:pacing', [
            '--savings' => 10,
            '--redemptions_per_week' => 5,
            '--scan_xp_per_day' => 50,
            '--game_xp_per_day' => 25,
        ])
            ->expectsOutputToContain('XP / Level Pacing Report')
            ->expectsOutputToContain('$10.00')
            ->assertExitCode(0);
    }
}
