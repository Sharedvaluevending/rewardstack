<?php

namespace Tests\Feature\Console;

use App\Services\PrintfulService;
use Tests\TestCase;

class PrecachePrintfulMockupsCommandTest extends TestCase
{
    public function test_command_exits_success_when_precache_succeeds(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('precacheCommonMockups')->once();
        });

        $this->artisan('app:precache-printful-mockups')
            ->expectsOutputToContain('Starting Printful mockup pre-caching')
            ->expectsOutputToContain('Mockup pre-caching completed successfully')
            ->assertExitCode(0);
    }

    public function test_command_accepts_force_option(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('precacheCommonMockups')->once();
        });

        $this->artisan('app:precache-printful-mockups', ['--force' => true])
            ->assertExitCode(0);
    }

    public function test_command_exits_failure_when_precache_throws(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('precacheCommonMockups')
                ->once()
                ->andThrow(new \RuntimeException('API error'));
        });

        $this->artisan('app:precache-printful-mockups')
            ->expectsOutputToContain('Failed to pre-cache mockups')
            ->assertExitCode(1);
    }
}
