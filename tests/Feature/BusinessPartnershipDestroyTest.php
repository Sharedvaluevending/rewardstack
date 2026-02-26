<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPartnershipDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroying_partnership_deactivates_cross_promos_between_businesses(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create(['user_id' => $owner1->id, 'is_testing_account' => true]);

        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create(['user_id' => $owner2->id, 'is_testing_account' => true]);

        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        $p1 = Promotion::factory()->create(['business_id' => $business1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);
        $p2 = Promotion::factory()->create(['business_id' => $business2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'DESTROY1',
            'name' => 'Deal chain',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($owner1)->delete("/business/partnerships/{$partnership->id}");
        $resp->assertStatus(302);

        $crossPromo->refresh();
        $this->assertFalse((bool) $crossPromo->is_active);
        $this->assertSame(CrossPromotion::STATUS_DECLINED, $crossPromo->status);

        $this->assertDatabaseMissing('business_partnerships', [
            'id' => $partnership->id,
        ]);
    }
}

