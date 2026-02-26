<?php

namespace Tests\Unit\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CleanupExpiredRewardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_expired_rewards_exits_success_when_empty(): void
    {
        $exitCode = Artisan::call('rewards:cleanup-expired');

        $this->assertSame(0, $exitCode);
    }

    public function test_cleanup_expired_rewards_dry_run_exits_success(): void
    {
        $exitCode = Artisan::call('rewards:cleanup-expired', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
    }
}
