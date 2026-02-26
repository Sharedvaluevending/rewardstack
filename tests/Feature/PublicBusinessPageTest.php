<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\StackableEntry;
use App\Models\StackablePool;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBusinessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_business_page_renders_and_includes_promos_stackables_and_featured_promo_when_enabled(): void
    {
        // Enable featured_promo via canonical plan record
        SubscriptionPlan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Pro plan',
                'monthly_price' => 99,
                'yearly_price' => 999,
                'features' => ['featured_promo' => true],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
            ]
        );

        $business = Business::factory()->create([
            'is_active' => true,
            'subscription_tier' => 'pro',
            'city' => 'New York',
            'state' => 'NY',
            'address_line1' => '123 Main St',
            'settings' => [],
        ]);

        $featured = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $business->update([
            'settings' => ['featured_promotion_id' => $featured->id],
        ]);

        $p2 = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $pool = StackablePool::create([
            'name' => 'Pool',
            'code' => 'POOLBIZ1',
            'description' => 'Pool',
            'is_active' => true,
            'city' => 'New York',
        ]);

        StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => $p2->id,
            'sort_order' => 1,
            'is_featured' => true,
            'is_active' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->get('/b/' . $business->slug)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/Business')
                    ->where('business.id', $business->id)
                    ->has('promotions', 2)
                    ->has('stackables', 1)
                    ->where('featuredPromo.id', $featured->id)
            );
    }

    public function test_inactive_business_404s(): void
    {
        $business = Business::factory()->create(['is_active' => false]);
        $this->get('/b/' . $business->slug)->assertStatus(404);
    }
}

