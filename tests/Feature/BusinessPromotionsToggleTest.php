<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPromotionsToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_toggle_own_promotion_active_state(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post("/business/promotions/{$promotion->id}/toggle")
            ->assertStatus(302);

        $promotion->refresh();
        $this->assertFalse((bool) $promotion->is_active);
    }
}

