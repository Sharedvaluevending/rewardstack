<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Notifications\ReferralCommissionEarned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCommissionEarnedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_and_database_channels(): void
    {
        $referrer = User::factory()->create();
        $business = Business::factory()->create();
        $owner = $business->owner;
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'business_user_id' => $owner->id,
            'referral_code' => 'REF' . uniqid(),
            'status' => Referral::STATUS_ACTIVE,
        ]);
        $commission = ReferralCommission::create([
            'referral_id' => $referral->id,
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'subscription_period' => now()->format('Y-m'),
            'business_payment' => 100,
            'commission_rate' => 10,
            'commission_amount' => 10,
            'status' => 'pending',
        ]);
        $notification = new ReferralCommissionEarned($commission);
        $this->assertSame(['mail', 'database'], $notification->via(new \stdClass()));
    }
}
