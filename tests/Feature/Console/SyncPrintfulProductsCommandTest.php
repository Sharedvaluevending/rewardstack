<?php

namespace Tests\Feature\Console;

use App\Services\PrintfulService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPrintfulProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_with_product_ids_option(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getProductVariants')
                ->with(71)
                ->once()
                ->andReturn([]);
        });

        $this->artisan('printful:sync', ['--product-ids' => '71'])
            ->expectsOutputToContain('Syncing specific product IDs')
            ->assertExitCode(0);
    }

    public function test_command_uses_markup_option(): void
    {
        $this->mock(PrintfulService::class, function ($mock) {
            $mock->shouldReceive('getProductVariants')->andReturn([]);
        });

        $this->artisan('printful:sync', [
            '--product-ids' => '71',
            '--markup' => 20,
        ])
            ->expectsOutputToContain('20% markup')
            ->assertExitCode(0);
    }
}
