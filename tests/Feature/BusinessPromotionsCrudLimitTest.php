<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessPromotionsCrudLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotions_pages_load_and_toggle_delete_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/promotions')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Promotions/Index'));

        $this->actingAs($owner)
            ->get('/business/promotions/create')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Promotions/Create'));

        $this->actingAs($owner)
            ->post('/business/promotions', [
                'name' => 'Promo 1',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'is_active' => true,
            ])
            ->assertStatus(302);

        $promotion = Promotion::where('business_id', $business->id)->first();
        $this->assertNotNull($promotion);

        $this->actingAs($owner)
            ->get('/business/promotions/' . $promotion->id)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Promotions/Show'));

        $this->actingAs($owner)
            ->post("/business/promotions/{$promotion->id}/toggle")
            ->assertStatus(302);

        $promotion->refresh();
        $this->assertFalse((bool) $promotion->is_active);

        $this->actingAs($owner)
            ->delete('/business/promotions/' . $promotion->id)
            ->assertStatus(302);

        $this->assertSoftDeleted('promotions', ['id' => $promotion->id]);
    }

    public function test_promotion_plan_limit_is_enforced(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'starter', // seeded starter limit: promotions = 5
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        Promotion::factory()->count(5)->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->post('/business/promotions', [
                'name' => 'Over limit',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'is_active' => true,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['limit']);
    }
}

