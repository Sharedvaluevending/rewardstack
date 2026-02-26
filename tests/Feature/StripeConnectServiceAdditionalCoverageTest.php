<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StripeConnectServiceAdditionalCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));
        config(['stripe.connect.enabled' => true, 'services.stripe.secret' => 'sk_test_fake']);
    }

    public function test_process_payouts_skips_processing_payout_with_transaction_id(): void
    {
        config(['stripe.connect.auto_payout_minimum' => 25]);

        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_123',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
        ]);

        // Existing processing payout with a transaction_id => should skip safely
        ReferralPayout::create([
            'user_id' => $user->id,
            'amount' => 30,
            'method' => 'stripe',
            'destination' => $user->stripe_connect_id,
            'status' => ReferralPayout::STATUS_PROCESSING,
            'transaction_id' => 'tr_already',
            'requested_at' => now(),
        ]);

        // Approved commissions exist but should not be processed due to skip
        $business = Business::factory()->create();
        $businessUser = User::factory()->create(['role' => 'business']);
        $business->update(['user_id' => $businessUser->id]);

        $referral = Referral::create([
            'referrer_id' => $user->id,
            'business_id' => $business->id,
            'business_user_id' => $businessUser->id,
            'referral_code' => 'TEST-' . uniqid(),
            'commission_rate' => 10.00,
            'status' => 'active',
        ]);

        ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $user->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100.00,
            'commission_rate' => 10.00,
            'commission_amount' => 30.00,
            'status' => ReferralCommission::STATUS_APPROVED,
        ]);

        $mockStripe = \Mockery::mock(\Stripe\StripeClient::class);
        $svc = new StripeConnectService($mockStripe);

        $res = $svc->processAutomaticPayouts();
        $this->assertSame(0, $res['processed']);
        $this->assertSame(0, $res['failed']);
        $this->assertSame(1, $res['skipped']);

        // No new payout created
        $this->assertSame(1, ReferralPayout::where('user_id', $user->id)->count());
    }

    public function test_process_payouts_handles_unexpected_exception_and_counts_failed(): void
    {
        config(['stripe.connect.auto_payout_minimum' => 25]);

        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_456',
            'stripe_connect_onboarded' => true,
            'payout_method' => 'stripe',
        ]);

        $business = Business::factory()->create();
        $businessUser = User::factory()->create(['role' => 'business']);
        $business->update(['user_id' => $businessUser->id]);

        $referral = Referral::create([
            'referrer_id' => $user->id,
            'business_id' => $business->id,
            'business_user_id' => $businessUser->id,
            'referral_code' => 'TEST-' . uniqid(),
            'commission_rate' => 10.00,
            'status' => 'active',
        ]);

        ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $user->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100.00,
            'commission_rate' => 10.00,
            'commission_amount' => 30.00,
            'status' => ReferralCommission::STATUS_APPROVED,
        ]);

        $mockStripe = \Mockery::mock(\Stripe\StripeClient::class);

        $svc = \Mockery::mock(StripeConnectService::class, [$mockStripe])->makePartial();
        $svc->shouldReceive('createPayout')->andThrow(new \Exception('boom'));

        $res = $svc->processAutomaticPayouts();
        $this->assertSame(0, $res['processed']);
        $this->assertSame(1, $res['failed']);
    }
}

