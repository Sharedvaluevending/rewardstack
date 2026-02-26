<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardLeaderboardPrizesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_when_no_leaderboards_need_awards(): void
    {
        $this->artisan('leaderboards:award-prizes')
            ->expectsOutputToContain('Checking for leaderboards that need prize awards')
            ->expectsOutputToContain('No leaderboards need prize awards at this time.')
            ->assertExitCode(0);
    }
}
