<?php

namespace Tests\Unit\Console;

use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessReferralPayoutsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_returns_zero_when_stripe_connect_not_enabled(): void
    {
        $stripeConnect = $this->mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('isEnabled')->once()->andReturn(false);

        $exit = $this->artisan('referrals:process-payouts');

        $exit->assertSuccessful();
    }

    public function test_handle_returns_zero_when_enabled_and_processes(): void
    {
        $stripeConnect = $this->mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('isEnabled')->once()->andReturn(true);
        $stripeConnect->shouldReceive('processAutomaticPayouts')->once()->andReturn([
            'message' => 'Done',
            'processed' => 0,
            'failed' => 0,
        ]);

        $exit = $this->artisan('referrals:process-payouts');

        $exit->assertSuccessful();
    }
}
