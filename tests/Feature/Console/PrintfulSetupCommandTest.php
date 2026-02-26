<?php

namespace Tests\Feature\Console;

use App\Services\PrintfulService;
use Tests\TestCase;

class PrintfulSetupCommandTest extends TestCase
{
    public function test_info_option_exits_failure_when_no_stores(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getStoreInfo')->once()->andReturn([]);
        });

        $this->artisan('printful:setup', ['--info' => true])
            ->expectsOutputToContain('No stores found')
            ->assertExitCode(1);
    }

    public function test_info_option_exits_success_when_store_exists(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getStoreInfo')->once()->andReturn([
                ['id' => 1, 'name' => 'Test Store', 'type' => 'store', 'website' => 'https://example.com', 'created' => '2024-01-01'],
            ]);
        });

        $this->artisan('printful:setup', ['--info' => true])
            ->expectsOutputToContain('Connected to Printful successfully')
            ->assertExitCode(0);
    }

    public function test_default_run_shows_store_info_and_webhooks(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getStoreInfo')->once()->andReturn([
                ['id' => 1, 'name' => 'Test Store', 'type' => 'store', 'website' => '', 'created' => ''],
            ]);
            $mock->shouldReceive('getWebhooks')->once()->andReturn(['url' => 'https://app.example.com/webhooks', 'types' => ['order.created']]);
        });

        $this->artisan('printful:setup')
            ->expectsOutputToContain('Fetching Printful store information')
            ->expectsOutputToContain('Checking registered webhooks')
            ->assertExitCode(0);
    }
}
