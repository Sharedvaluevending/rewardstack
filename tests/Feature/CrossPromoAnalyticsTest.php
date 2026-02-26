<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_dashboard_loads()
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business1 = Business::factory()->create([
            'user_id' => $user->id,
            'is_testing_account' => true, // bypass subscription.active middleware in tests
        ]);
        $business2 = Business::factory()->create();
        
        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        
        $crossPromo = CrossPromotion::create([
            'code' => 'ANALYTICS',
            'name' => 'Analytics Test',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => 'split',
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);
        
        $qrCode = QRCode::create([
            'business_id' => $business1->id,
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'name' => 'Test QR',
            'code' => 'TESTQR',
        ]);

        $this->actingAs($user, 'web');
        $this->assertAuthenticatedAs($user, 'web');

        $response = $this->get("/business/partnerships/cross-promo/{$crossPromo->id}/analytics");

        // Debug helper (disabled by default):
        // fwrite(STDERR, "Redirect Location: " . ($response->headers->get('Location') ?? 'none') . PHP_EOL);
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Business/Partnerships/CrossPromoAnalytics'));
    }
}
