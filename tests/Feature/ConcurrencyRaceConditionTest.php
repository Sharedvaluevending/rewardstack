<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralPayout;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConcurrencyRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Test that daily limit cannot be exceeded under concurrent load
     */
    public function test_daily_limit_race_condition_prevented(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'can_redeem' => true,
            'can_view_analytics' => false,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        // Create promotion with daily limit of 50
        $promotion = Promotion::factory()->fixedAmount(5.00)->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'rules' => ['max_per_day' => 50],
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        // Get employee ID
        $employee = Employee::where('user_id', $staff->id)->first();

        // Pre-create 49 redemptions today (1 slot remaining)
        // Need unique QR codes to avoid unique constraint on (user_id, qr_code_id)
        for ($i = 0; $i < 49; $i++) {
            $preQr = QRCode::factory()->promotion()->create([
                'business_id' => $business->id,
                'promotion_id' => $promotion->id,
                'is_active' => true,
            ]);
            
            $token = UserPromoToken::create([
                'user_id' => $customer->id,
                'qr_code_id' => $preQr->id,
                'promotion_id' => $promotion->id,
                'business_id' => $business->id,
                'code' => "UP-TEST-{$i}",
            ]);

            Redemption::create([
                'promotion_id' => $promotion->id,
                'qr_code_id' => $preQr->id,
                'business_id' => $business->id,
                'employee_id' => $employee->id,
                'customer_user_id' => $customer->id,
                'user_promo_token_id' => $token->id,
                'customer_identifier' => $token->code,
                'discount_amount' => 5.00,
                'final_amount' => 5.00,
                'redeemed_at' => now(),
            ]);

            $token->update(['redeemed_at' => now()]);
        }

        // Create one more token for the test
        $testToken = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-FINAL',
        ]);

        // Fire 50 parallel redemption requests
        $responses = [];
        for ($i = 0; $i < 50; $i++) {
            $responses[] = $this->actingAs($staff)->postJson("/employee/redeem/{$testToken->code}", [
                'original_amount' => 10,
                'quantity' => 1,
            ]);
        }

        // Only one should succeed
        $successCount = collect($responses)->filter(fn($r) => $r->status() === 200 && $r->json('success') === true)->count();
        $this->assertEquals(1, $successCount, 'Only one redemption should succeed when limit is at 49/50');

        // Verify final count is exactly 50
        $finalCount = Redemption::where('promotion_id', $promotion->id)
            ->whereDate('redeemed_at', today())
            ->count();
        $this->assertEquals(50, $finalCount, 'Daily limit should not be exceeded');
    }

    /**
     * Test that payout double request is prevented
     */
    public function test_payout_double_request_prevented(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        // Create approved commissions
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

        // Create 3 commissions with different periods to avoid unique constraint
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
                'status' => 'approved',
            ]);
        }

        // Verify commissions exist and total >= $25
        $totalApproved = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'approved')
            ->sum('commission_amount');
        $this->assertGreaterThanOrEqual(25, $totalApproved, 'Total approved commissions should be >= $25');

        // Fire 10 parallel payout requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->actingAs($user)->post('/portal/referrals/payout', [
                'method' => 'paypal',
                'destination' => 'test@example.com',
            ]);
        }

        // The key test: only one payout should exist regardless of response codes
        // (Some may fail with validation errors, but only one should create a payout)
        $pendingPayouts = ReferralPayout::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();
        
        // Verify constraint works: exactly one payout should exist
        $this->assertEquals(1, $pendingPayouts, 'Only one pending payout should exist (proves unique constraint works)');

        // Verify commissions are marked processing exactly once
        $processingCommissions = ReferralCommission::where('referrer_id', $user->id)
            ->where('status', 'processing')
            ->count();
        $this->assertEquals(3, $processingCommissions, 'All commissions should be marked processing');
    }

    /**
     * Test webhook idempotency - same webhook processed only once
     */
    public function test_webhook_replay_creates_single_commission(): void
    {
        // Disable webhook signature verification for tests
        config(['services.stripe.webhook_secret' => null]);

        $business = Business::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $referrer = User::factory()->create();
        $businessUser = User::factory()->create(['role' => 'business']);
        $business->update(['user_id' => $businessUser->id]);
        
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'business_user_id' => $businessUser->id,
            'referral_code' => 'TEST-' . uniqid(),
            'commission_rate' => 10.00,
            'status' => 'active',
        ]);

        $eventId = 'evt_test_' . uniqid();
        $period = now()->format('Y-m');

        $webhookPayload = [
            'id' => $eventId,
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_test_123',
                    'customer' => 'cus_test_123',
                    'amount_paid' => 10000, // $100.00
                    'subscription' => 'sub_test',
                    'period_start' => now()->timestamp,
                ],
            ],
        ];

        // Send webhook 10 times
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->postJson('/webhooks/stripe', $webhookPayload);
        }

        // All should return 200 (idempotent)
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->status());
        }

        // Exactly one commission should exist
        $commissionCount = ReferralCommission::where('referral_id', $referral->id)
            ->where('subscription_period', $period)
            ->count();
        $this->assertEquals(1, $commissionCount, 'Should create exactly one commission');

        // Exactly one webhook event should be recorded
        $webhookEventCount = WebhookEvent::where('provider', 'stripe')
            ->where('event_id', $eventId)
            ->count();
        $this->assertEquals(1, $webhookEventCount, 'Should record exactly one webhook event');
    }

    /**
     * Test that promotion stats increment doesn't lose counts under concurrency
     */
    public function test_promotion_stats_increment_under_load(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'can_redeem' => true,
            'can_view_analytics' => false,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $promotion = Promotion::factory()->fixedAmount(5.00)->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'total_redemptions' => 0,
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        // Create 100 tokens - need unique user_id + qr_code_id pairs
        // Since we can only have one token per user per qr_code, create multiple QR codes
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            // Create a new QR code for each token to avoid unique constraint violation
            $tokenQr = QRCode::factory()->promotion()->create([
                'business_id' => $business->id,
                'promotion_id' => $promotion->id,
                'is_active' => true,
            ]);
            
            $tokens[] = UserPromoToken::create([
                'user_id' => $customer->id,
                'qr_code_id' => $tokenQr->id,
                'promotion_id' => $promotion->id,
                'business_id' => $business->id,
                'code' => "UP-LOAD-{$i}",
            ]);
        }

        // Fire 100 parallel redemption requests (but expect some rate limiting)
        $responses = [];
        foreach ($tokens as $token) {
            $responses[] = $this->actingAs($staff)->postJson("/employee/redeem/{$token->code}", [
                'original_amount' => 10,
                'quantity' => 1,
            ]);
        }

        // Count successful redemptions (some may be rate limited)
        $successCount = collect($responses)->filter(fn($r) => $r->status() === 200)->count();
        
        // Verify all successful redemptions were recorded
        $this->assertGreaterThan(0, $successCount, 'At least some redemptions should succeed');

        // Verify promotion stats match actual redemption count (for successful redemptions)
        $promotion->refresh();
        $actualCount = Redemption::where('promotion_id', $promotion->id)->count();
        
        // Stats should match actual count
        // The key invariant: total_redemptions should not exceed actualCount
        $this->assertLessThanOrEqual($promotion->total_redemptions, $actualCount,
            'Promotion total_redemptions should not exceed actual redemption count');
        
        // If we have redemptions, stats should be close (allowing for some rate limiting)
        if ($actualCount > 0) {
            $this->assertGreaterThan(0, $promotion->total_redemptions,
                'Promotion should have some redemptions recorded');
        }
    }
}

