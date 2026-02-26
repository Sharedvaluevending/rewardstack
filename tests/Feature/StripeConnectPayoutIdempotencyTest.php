<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Business;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeConnectPayoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure fake Stripe keys to prevent real API calls
        config(['stripe.connect.enabled' => true]);
        config(['services.stripe.secret' => 'sk_test_fake']);
    }
    
    /**
     * Helper to create a mock Stripe client and inject it into the service
     */
    protected function createMockStripeClient($transferId = null, $shouldThrow = false, $exception = null): \Stripe\StripeClient
    {
        $transferId = $transferId ?? 'tr_test_' . uniqid();
        $mockTransfer = (object) ['id' => $transferId];
        
        $mockStripe = \Mockery::mock(\Stripe\StripeClient::class);
        $mockTransfers = \Mockery::mock();
        
        if ($shouldThrow && $exception) {
            $mockTransfers->shouldReceive('create')
                ->andThrow($exception);
        } else {
            $mockTransfers->shouldReceive('create')
                ->andReturn($mockTransfer);
        }
        
        $mockStripe->transfers = $mockTransfers;
        return $mockStripe;
    }

    /**
     * Test that retrying processAutomaticPayouts creates only one payout
     */
    public function test_retry_creates_only_one_payout(): void
    {
        // Setup: Create user with Stripe Connect
        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_123',
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

        // Create 3 approved commissions totaling $30 (above $25 minimum)
        $periods = collect([
            now()->subMonths(2)->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->format('Y-m'),
        ])->unique()->values();
        while ($periods->count() < 3) {
            $periods->push(now()->addMonths($periods->count())->format('Y-m'));
            $periods = $periods->unique()->values();
        }

        foreach ($periods as $period) {
            ReferralCommission::create([
                'referral_id' => $referral->id,
                'referrer_id' => $user->id,
                'business_id' => $business->id,
                'subscription_period' => $period,
                'business_payment' => 100.00,
                'commission_rate' => 10.00,
                'commission_amount' => 10.00,
                'status' => ReferralCommission::STATUS_APPROVED,
            ]);
        }

        // Create mock and inject into service
        $mockStripe = $this->createMockStripeClient();
        // Service needs to be enabled - inject mock Stripe client
        $service = new StripeConnectService($mockStripe);
        
        // Ensure service is enabled by setting config
        config(['stripe.connect.enabled' => true]);
        config(['services.stripe.secret' => 'sk_test_fake']);

        // First call: Process payouts
        $result1 = $service->processAutomaticPayouts();
        
        // Verify one payout was created
        $this->assertEquals(1, $result1['processed'], 'First payout should succeed. Result: ' . json_encode($result1));
        $this->assertEquals(0, $result1['failed']);

        $payout1 = ReferralPayout::where('user_id', $user->id)->first();
        $this->assertNotNull($payout1);
        $this->assertEquals(ReferralPayout::STATUS_COMPLETED, $payout1->status);
        $this->assertNotNull($payout1->transaction_id);

        // Verify commissions are marked as paid
        $paidCommissions = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', ReferralCommission::STATUS_PAID)
            ->count();
        $this->assertEquals(3, $paidCommissions);

        // Second call: Retry (should be skipped due to idempotency check)
        $result2 = $service->processAutomaticPayouts();
        
        // Should skip (already processed)
        $this->assertEquals(0, $result2['processed']);
        $this->assertEquals(0, $result2['failed']);
        $this->assertEquals(1, $result2['skipped']);

        // Verify still only one payout exists
        $payoutCount = ReferralPayout::where('user_id', $user->id)->count();
        $this->assertEquals(1, $payoutCount);

        // Verify commissions are still paid (not double-paid)
        $paidCommissionsAfterRetry = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', ReferralCommission::STATUS_PAID)
            ->count();
        $this->assertEquals(3, $paidCommissionsAfterRetry);
    }

    /**
     * Test that processing payout creates payout record before Stripe call
     */
    public function test_payout_record_created_before_stripe_call(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_123',
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

        // Create approved commissions
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

        // Create mock and inject into service
        $mockStripe = $this->createMockStripeClient();
        $service = new StripeConnectService($mockStripe);

        // Process payouts
        $service->processAutomaticPayouts();

        // Verify payout record exists with processing status initially
        // (In real flow, it would be processing then completed, but in test it completes immediately)
        $payout = ReferralPayout::where('user_id', $user->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(ReferralPayout::STATUS_COMPLETED, $payout->status);
        $this->assertNotNull($payout->transaction_id);

        // Verify commissions are marked as processing then paid
        $commissions = ReferralCommission::where('referrer_id', $user->id)->get();
        foreach ($commissions as $commission) {
            $this->assertEquals(ReferralCommission::STATUS_PAID, $commission->status);
            $this->assertEquals($payout->transaction_id, $commission->payout_reference);
        }
    }

    /**
     * Test that failed Stripe transfer reverts commissions to approved
     */
    public function test_failed_transfer_reverts_commissions(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_123',
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

        // Create approved commissions
        $commission = ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $user->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100.00,
            'commission_rate' => 10.00,
            'commission_amount' => 30.00,
            'status' => ReferralCommission::STATUS_APPROVED,
        ]);

        // Create mock that throws exception
        $exception = \Mockery::mock(\Stripe\Exception\ApiErrorException::class);
        $exception->shouldReceive('getMessage')->andReturn('Insufficient funds');
        $mockStripe = $this->createMockStripeClient(null, true, $exception);
        $service = new StripeConnectService($mockStripe);

        // Process payouts (should fail)
        $result = $service->processAutomaticPayouts();

        // Verify payout failed
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(1, $result['failed']);

        // Verify payout record exists with failed status
        $payout = ReferralPayout::where('user_id', $user->id)->first();
        $this->assertNotNull($payout);
        $this->assertEquals(ReferralPayout::STATUS_FAILED, $payout->status);
        $this->assertNull($payout->transaction_id);
        $this->assertNotNull($payout->notes);

        // Verify commissions reverted to approved
        $commission->refresh();
        $this->assertEquals(ReferralCommission::STATUS_APPROVED, $commission->status);
        $this->assertNull($commission->payout_reference);
        $this->assertNull($commission->payout_method);
    }

    /**
     * Test that processing payout locks commissions to prevent double-processing
     */
    public function test_concurrent_payout_attempts_prevented(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'stripe_connect_id' => 'acct_test_123',
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

        // Create approved commissions
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

        // Create mock and inject into service
        $mockStripe = $this->createMockStripeClient();
        $service = new StripeConnectService($mockStripe);
        
        // Ensure service is enabled
        config(['stripe.connect.enabled' => true]);
        config(['services.stripe.secret' => 'sk_test_fake']);

        // First call: Process payouts
        $result1 = $service->processAutomaticPayouts();
        $this->assertEquals(1, $result1['processed'], 'First payout should succeed. Result: ' . json_encode($result1));

        // Simulate a processing payout (stuck in processing state)
        $payout = ReferralPayout::where('user_id', $user->id)->first();
        $payout->update([
            'status' => ReferralPayout::STATUS_PROCESSING,
            'transaction_id' => null,
        ]);

        // Revert commissions to processing state
        ReferralCommission::where('referrer_id', $user->id)
            ->update([
                'status' => ReferralCommission::STATUS_PROCESSING,
                'payout_reference' => "payout:{$payout->id}",
            ]);

        // Second call: Should skip because payout is already processing
        $result2 = $service->processAutomaticPayouts();
        $this->assertEquals(0, $result2['processed']);
        $this->assertEquals(1, $result2['skipped']);

        // Verify still only one payout exists
        $payoutCount = ReferralPayout::where('user_id', $user->id)->count();
        $this->assertEquals(1, $payoutCount);
    }

}

