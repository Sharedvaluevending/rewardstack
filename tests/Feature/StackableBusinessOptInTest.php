<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StackableBusinessOptInTest extends TestCase
{
    use RefreshDatabase;

    public function test_growth_plus_business_can_set_and_swap_stackable_promotion_and_remove(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'is_testing_account' => true,
            'subscription_tier' => 'growth',
        ]);

        $p1 = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true, 'is_stackable' => false]);
        $p2 = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true, 'is_stackable' => false]);

        \App\Models\QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $p1->id,
            'type' => 'stackable',
            'is_active' => true,
        ]);
        \App\Models\QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $p2->id,
            'type' => 'stackable',
            'is_active' => true,
        ]);

        $set1 = $this->actingAs($owner)->post('/business/stackable/set', [
            'promotion_id' => $p1->id,
        ]);
        $set1->assertStatus(302);

        $p1->refresh();
        $p2->refresh();
        $this->assertTrue((bool) $p1->is_stackable);
        $this->assertFalse((bool) $p2->is_stackable);

        // Swap to another promotion
        $set2 = $this->actingAs($owner)->post('/business/stackable/set', [
            'promotion_id' => $p2->id,
        ]);
        $set2->assertStatus(302);

        $p1->refresh();
        $p2->refresh();
        $this->assertFalse((bool) $p1->is_stackable);
        $this->assertTrue((bool) $p2->is_stackable);

        $remove = $this->actingAs($owner)->post('/business/stackable/remove');
        $remove->assertStatus(302);

        $p1->refresh();
        $p2->refresh();
        $this->assertFalse((bool) $p1->is_stackable);
        $this->assertFalse((bool) $p2->is_stackable);
    }

    public function test_starter_business_cannot_enable_stackable(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $owner = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create([
            'user_id' => $owner->id,
            'is_testing_account' => true,
            'subscription_tier' => 'starter',
        ]);

        $promo = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true, 'is_stackable' => false]);
        \App\Models\QRCode::factory()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'type' => 'stackable',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($owner)->post('/business/stackable/set', [
            'promotion_id' => $promo->id,
        ]);

        $resp->assertStatus(302);
        $resp->assertSessionHasErrors(['subscription']);

        $promo->refresh();
        $this->assertFalse((bool) $promo->is_stackable);
    }
}

