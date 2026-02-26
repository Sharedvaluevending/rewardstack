<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessPartnership;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CrossPromoMutualFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_promo_mutual_flow_create_accept_pause_resume(): void
    {
        Notification::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner1 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create([
            'user_id' => $owner1->id,
            'is_testing_account' => true,
        ]);

        $owner2 = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business2 = Business::factory()->create([
            'user_id' => $owner2->id,
            'is_testing_account' => true,
        ]);

        // Accepted partnership is required before requesting cross-promo
        $partnership = BusinessPartnership::create([
            'requester_business_id' => $business1->id,
            'partner_business_id' => $business2->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
        $this->assertNotNull($partnership->id);

        $promo1 = Promotion::factory()->create([
            'business_id' => $business1->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
        $promo2 = Promotion::factory()->create([
            'business_id' => $business2->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        $create = $this->actingAs($owner1)->post('/business/partnerships/cross-promo', [
            'partner_business_id' => $business2->id,
            'name' => 'Partner Deal Chain',
            'my_promotion_id' => $promo1->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => $promo1->id,
        ]);
        $create->assertStatus(302);

        $crossPromo = CrossPromotion::query()->first();
        $this->assertNotNull($crossPromo);
        $this->assertSame(CrossPromotion::STATUS_PENDING, $crossPromo->status);
        $this->assertFalse((bool) $crossPromo->is_active);
        $this->assertSame($business1->id, (int) $crossPromo->business_1_id);
        $this->assertSame($business2->id, (int) $crossPromo->business_2_id);
        $this->assertSame($promo1->id, (int) $crossPromo->promotion_1_id);
        $this->assertNull($crossPromo->promotion_2_id);
        $this->assertSame(CrossPromotion::CHAIN_SEQUENTIAL, $crossPromo->chain_mode);
        $this->assertSame($promo1->id, (int) $crossPromo->primary_promotion_id);

        $accept = $this->actingAs($owner2)->post("/business/partnerships/cross-promo/{$crossPromo->id}/accept", [
            'my_promotion_id' => $promo2->id,
            'agree' => true,
        ]);
        $accept->assertStatus(302);

        $crossPromo->refresh();
        $this->assertSame(CrossPromotion::STATUS_ACCEPTED, $crossPromo->status);
        $this->assertTrue((bool) $crossPromo->is_active);
        $this->assertSame($promo2->id, (int) $crossPromo->promotion_2_id);
        $this->assertNotNull($crossPromo->accepted_at);

        // Pause/resume toggles is_active
        $pause = $this->actingAs($owner1)->post("/business/partnerships/cross-promo/{$crossPromo->id}/pause");
        $pause->assertStatus(302);
        $crossPromo->refresh();
        $this->assertFalse((bool) $crossPromo->is_active);

        $resume = $this->actingAs($owner2)->post("/business/partnerships/cross-promo/{$crossPromo->id}/resume");
        $resume->assertStatus(302);
        $crossPromo->refresh();
        $this->assertTrue((bool) $crossPromo->is_active);
    }
}

