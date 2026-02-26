<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Maintenance\ProcessReferralPayoutsJob;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessReferralPayoutsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_returns_early_when_stripe_connect_not_enabled(): void
    {
        $stripe = Mockery::mock(StripeConnectService::class);
        $stripe->shouldReceive('isEnabled')->once()->andReturn(false);
        $stripe->shouldNotReceive('processAutomaticPayouts');

        $job = new ProcessReferralPayoutsJob();
        $job->handle($stripe);

        $this->assertTrue(true, 'Handle completed without calling processAutomaticPayouts');
    }

    public function test_handle_calls_process_automatic_payouts_when_enabled(): void
    {
        $stripe = Mockery::mock(StripeConnectService::class);
        $stripe->shouldReceive('isEnabled')->once()->andReturn(true);
        $stripe->shouldReceive('processAutomaticPayouts')->once()->andReturn([
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ]);

        $job = new ProcessReferralPayoutsJob();
        $job->handle($stripe);

        $this->assertTrue(true, 'Handle called processAutomaticPayouts when enabled');
    }
}
