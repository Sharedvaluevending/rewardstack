<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('active', Referral::STATUS_ACTIVE);
        $this->assertSame('paused', Referral::STATUS_PAUSED);
        $this->assertSame('cancelled', Referral::STATUS_CANCELLED);
    }

    public function test_scope_active_filters_by_status(): void
    {
        $referrer = User::factory()->create();
        $business = Business::factory()->create();
        $owner = $business->owner;
        $business2 = Business::factory()->create();
        $owner2 = $business2->owner;
        Referral::create([
            'referrer_id' => $referrer->id,
            'business_id' => $business->id,
            'business_user_id' => $owner->id,
            'referral_code' => 'CODE1',
            'status' => Referral::STATUS_ACTIVE,
        ]);
        Referral::create([
            'referrer_id' => $referrer->id,
            'business_id' => $business2->id,
            'business_user_id' => $owner2->id,
            'referral_code' => 'CODE2',
            'status' => Referral::STATUS_CANCELLED,
        ]);
        $active = Referral::active()->get();
        $this->assertCount(1, $active);
        $this->assertSame(Referral::STATUS_ACTIVE, $active->first()->status);
    }
}
