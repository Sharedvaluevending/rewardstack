<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestPromotionFlowCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_runs_and_exits_success_with_cleanup(): void
    {
        $this->artisan('test:promotion-flow', ['--cleanup' => true])
            ->expectsOutputToContain('Starting comprehensive promotion flow test')
            ->expectsOutputToContain('Step 1: Creating test business and customer')
            ->assertExitCode(0);
    }

    public function test_command_runs_without_cleanup_option(): void
    {
        $this->artisan('test:promotion-flow')
            ->expectsOutputToContain('Test data preserved')
            ->assertExitCode(0);
    }
}
