<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPromotionsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_update_promotion(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        $this->actingAs($owner)
            ->put('/business/promotions/' . $promo->id, [
                'name' => 'Updated Promo',
                'description' => 'Updated desc',
                'terms' => 'Terms',
                'discount_type' => Promotion::TYPE_PERCENTAGE,
                'discount_value' => 15,
                'tiers' => null,
                'rules' => [],
                'starts_at' => now()->subDay()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
                'is_active' => true,
                'is_stackable' => false,
            ])
            ->assertStatus(302);

        $promo->refresh();
        $this->assertSame('Updated Promo', $promo->name);
        $this->assertSame('15.00', (string) $promo->discount_value);
    }
}

