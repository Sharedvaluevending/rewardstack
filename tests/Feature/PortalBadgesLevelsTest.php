<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalBadgesLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_badges_page_loads_and_can_toggle_featured_with_limit(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $badge = Badge::create([
            'name' => 'Test Badge',
            'slug' => 'test-badge-' . uniqid(),
            'category' => Badge::CATEGORY_ACHIEVEMENT,
            'rarity' => Badge::RARITY_COMMON,
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $userBadge = UserBadge::create([
            'user_id' => $customer->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'is_complete' => true,
            'is_featured' => false,
            'is_new' => true,
        ]);

        $this->actingAs($customer)
            ->get('/portal/badges')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Badges'));

        $this->actingAs($customer)
            ->post("/portal/badges/{$userBadge->id}/feature")
            ->assertStatus(302);

        $userBadge->refresh();
        $this->assertTrue((bool) $userBadge->is_featured);
    }

    public function test_levels_page_lists_accessible_and_upcoming_level_exclusive_promos(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'level' => 3, 'xp' => 0]);

        $promo = Promotion::factory()->create(['is_active' => true]);
        $promo2 = Promotion::factory()->create(['is_active' => true]);

        // Accessible at level 3
        $qr1 = QRCode::factory()->create([
            'type' => 'level_exclusive',
            'promotion_id' => $promo->id,
            'business_id' => $promo->business_id,
            'is_active' => true,
            'required_level' => 3,
        ]);

        // Upcoming (<= level + 2)
        $qr2 = QRCode::factory()->create([
            'type' => 'level_exclusive',
            'promotion_id' => $promo2->id,
            'business_id' => $promo2->business_id,
            'is_active' => true,
            'required_level' => 5,
        ]);

        $this->actingAs($customer)
            ->get('/portal/levels')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Levels')
                    ->where('accessiblePromotions.0.qr_code.id', $qr1->id)
                    ->where('upcomingPromotions.0.qr_code.id', $qr2->id)
            );
    }
}

