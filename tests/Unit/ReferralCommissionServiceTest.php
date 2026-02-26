<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Services\ReferralCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_commission_for_payment_returns_null_when_no_referral(): void
    {
        $business = Business::factory()->create();
        $service = new ReferralCommissionService();
        $result = $service->createCommissionForPayment($business, 100.00);
        $this->assertNull($result);
    }

    public function test_create_commission_for_payment_creates_commission_when_referral_exists(): void
    {
        $referrer = \App\Models\User::factory()->create();
        $businessOwner = \App\Models\User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = \App\Models\Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => \App\Models\Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        $service = new ReferralCommissionService();
        $period = now()->format('Y-m');

        $result = $service->createCommissionForPayment($business, 100.00, $period);

        $this->assertInstanceOf(\App\Models\ReferralCommission::class, $result);
        $this->assertEquals(10.0, (float) $result->commission_amount);
        $this->assertSame(\App\Models\ReferralCommission::STATUS_PENDING, $result->status);
    }

    public function test_create_commission_returns_existing_when_already_exists_for_period(): void
    {
        $referrer = \App\Models\User::factory()->create();
        $businessOwner = \App\Models\User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = \App\Models\Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => \App\Models\Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        $period = now()->format('Y-m');
        $existing = \App\Models\ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => $period,
            'business_payment' => 50,
            'commission_rate' => 10,
            'commission_amount' => 5,
            'status' => \App\Models\ReferralCommission::STATUS_PENDING,
        ]);
        $service = new ReferralCommissionService();

        $result = $service->createCommissionForPayment($business, 100.00, $period);

        $this->assertSame($existing->id, $result->id);
        $this->assertEquals(1, \App\Models\ReferralCommission::where('referral_id', $referral->id)->where('subscription_period', $period)->count());
    }

    public function test_approve_commission_updates_status(): void
    {
        $referrer = \App\Models\User::factory()->create();
        $businessOwner = \App\Models\User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = \App\Models\Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => \App\Models\Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        $commission = \App\Models\ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 10,
            'status' => \App\Models\ReferralCommission::STATUS_PENDING,
        ]);
        $service = new ReferralCommissionService();

        $ok = $service->approveCommission($commission);

        $this->assertTrue($ok);
        $commission->refresh();
        $this->assertSame(\App\Models\ReferralCommission::STATUS_APPROVED, $commission->status);
    }

    public function test_get_pending_commissions_returns_sum(): void
    {
        $referrer = \App\Models\User::factory()->create();
        $businessOwner = \App\Models\User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = \App\Models\Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => \App\Models\Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        \App\Models\ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 15,
            'status' => \App\Models\ReferralCommission::STATUS_PENDING,
        ]);
        $service = new ReferralCommissionService();

        $total = $service->getPendingCommissions($referrer->id);

        $this->assertSame(15.0, $total);
    }

    public function test_get_approved_commissions_returns_sum(): void
    {
        $referrer = \App\Models\User::factory()->create();
        $businessOwner = \App\Models\User::factory()->create(['role' => 'business']);
        $business = Business::factory()->create(['user_id' => $businessOwner->id]);
        $referral = \App\Models\Referral::create([
            'business_id' => $business->id,
            'referrer_id' => $referrer->id,
            'business_user_id' => $businessOwner->id,
            'referral_code' => 'TEST-' . uniqid(),
            'status' => \App\Models\Referral::STATUS_ACTIVE,
            'commission_rate' => 10.0,
        ]);
        \App\Models\ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 20,
            'status' => \App\Models\ReferralCommission::STATUS_APPROVED,
        ]);
        $service = new ReferralCommissionService();

        $total = $service->getApprovedCommissions($referrer->id);

        $this->assertSame(20.0, $total);
    }
}
