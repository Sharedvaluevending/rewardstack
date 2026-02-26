<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureCadPlanPricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_with_error_when_stripe_not_configured(): void
    {
        config(['services.stripe.secret' => null]);

        $this->artisan('stripe:ensure-cad-plan-prices')
            ->expectsOutputToContain('STRIPE_SECRET is not configured')
            ->assertExitCode(1);
    }
}
