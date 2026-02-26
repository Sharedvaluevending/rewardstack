<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\SavedQRCode;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalScansSavedPromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_scans_saved_promotions_excludes_redeemed_by_default_and_supports_search_and_page_size_clamp(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();

        $redeemedPromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activePromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $redeemedQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $redeemedPromo->id,
            'name' => 'Redeemed Promo QR',
            'is_active' => true,
        ]);
        $activeQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $activePromo->id,
            'name' => 'Active Promo QR',
            'is_active' => true,
        ]);

        SavedQRCode::create(['user_id' => $customer->id, 'qr_code_id' => $redeemedQr->id, 'saved_at' => now()->subDays(2)]);
        SavedQRCode::create(['user_id' => $customer->id, 'qr_code_id' => $activeQr->id, 'saved_at' => now()->subDay()]);

        // Ensure tokens exist so PortalScanController doesn't need to generate QR image files.
        UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $redeemedQr->id,
            'promotion_id' => $redeemedPromo->id,
            'business_id' => $business->id,
            'code' => 'UP-REDE-EMED',
            'qr_image_path' => 'qrcodes/fake.png',
            'redeemed_at' => now(),
        ]);
        UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $activeQr->id,
            'promotion_id' => $activePromo->id,
            'business_id' => $business->id,
            'code' => 'UP-ACTI-VE01',
            'qr_image_path' => 'qrcodes/fake.png',
            'redeemed_at' => null,
        ]);

        // Redemption record reinforces redeemed exclusion logic.
        Redemption::create([
            'promotion_id' => $redeemedPromo->id,
            'qr_code_id' => $redeemedQr->id,
            'business_id' => $business->id,
            'redeemed_by_user_id' => $business->user_id,
            'customer_user_id' => $customer->id,
            'original_amount' => 20,
            'discount_amount' => 4,
            'final_amount' => 16,
            'redeemed_at' => now(),
        ]);

        // One promo scan should show in history.
        Scan::create([
            'qr_code_id' => $activeQr->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        // A QRcade scan tied to a redeemed reward should be excluded from scan history.
        $qrcadeQr = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
            'is_active' => true,
            'game_enabled' => true,
        ]);
        $play = \App\Models\GamePlay::factory()->win()->create([
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrcadeQr->id,
        ]);
        GameReward::factory()->redeemed()->create([
            'game_play_id' => $play->id,
            'user_id' => $customer->id,
            'business_id' => $business->id,
        ]);
        Scan::create([
            'qr_code_id' => $qrcadeQr->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'scan_type' => Scan::TYPE_QRCADE_GAME,
            'scanned_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/scans?page_size=100&saved_q=Active')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    // Page size clamp (max 50).
                    ->where('scans.per_page', 50)
                    // Only the promo scan should remain (qrcade scan excluded due to redeemed reward).
                    ->has('scans.data', 1)
                    ->where('scans.data.0.qr_code.type', 'promotion')
                    // Saved promos default to excluding redeemed.
                    ->has('savedPromotions.data', 1)
                    ->where('savedPromotions.data.0.qr_code.id', $activeQr->id)
                    ->where('savedFilters.q', 'Active')
            );
    }

    public function test_portal_scans_saved_promotions_supports_redeemed_and_expired_status_filters(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();

        $redeemedPromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(10),
        ]);
        $expiredPromo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 7,
            'is_active' => true,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);

        $redeemedQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $redeemedPromo->id,
            'name' => 'Redeemed Promo QR',
            'is_active' => true,
        ]);
        $expiredQr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $expiredPromo->id,
            'name' => 'Expired Promo QR',
            'is_active' => true,
        ]);

        SavedQRCode::create(['user_id' => $customer->id, 'qr_code_id' => $redeemedQr->id, 'saved_at' => now()->subDays(2)]);
        SavedQRCode::create(['user_id' => $customer->id, 'qr_code_id' => $expiredQr->id, 'saved_at' => now()->subDay()]);

        UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $redeemedQr->id,
            'promotion_id' => $redeemedPromo->id,
            'business_id' => $business->id,
            'code' => 'UP-REDE-0001',
            'qr_image_path' => 'qrcodes/fake.png',
            'redeemed_at' => now(),
        ]);
        UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $expiredQr->id,
            'promotion_id' => $expiredPromo->id,
            'business_id' => $business->id,
            'code' => 'UP-EXPI-0001',
            'qr_image_path' => 'qrcodes/fake.png',
            'redeemed_at' => null,
        ]);

        Redemption::create([
            'promotion_id' => $redeemedPromo->id,
            'qr_code_id' => $redeemedQr->id,
            'business_id' => $business->id,
            'redeemed_by_user_id' => $business->user_id,
            'customer_user_id' => $customer->id,
            'original_amount' => 30,
            'discount_amount' => 5,
            'final_amount' => 25,
            'redeemed_at' => now(),
        ]);

        // Status=redeemed should include the redeemed promo.
        $this->actingAs($customer)
            ->get('/portal/scans?status=redeemed&page_size=50')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    ->has('savedPromotions.data', 1)
                    ->where('savedPromotions.data.0.qr_code.id', $redeemedQr->id)
            );

        // Status=expired should include the expired promo.
        $this->actingAs($customer)
            ->get('/portal/scans?status=expired&page_size=50')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    ->has('savedPromotions.data', 1)
                    ->where('savedPromotions.data.0.qr_code.id', $expiredQr->id)
            );
    }
}

