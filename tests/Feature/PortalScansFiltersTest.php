<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalScansFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_promo_only_filter_returns_only_cross_promo_scans(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();

        $promo = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        $promoQr = QRCode::factory()->promotion()->create(['business_id' => $business->id, 'promotion_id' => $promo->id, 'is_active' => true]);

        Scan::create([
            'qr_code_id' => $promoQr->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $cp = CrossPromotion::create([
            'code' => 'CPFILT01',
            'name' => 'CP',
            'business_1_id' => $business->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);
        $cpQr = QRCode::create([
            'business_id' => $business->id,
            'code' => 'CPFILTQR',
            'name' => 'CP QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $cp->id,
            'is_active' => true,
        ]);
        Scan::create([
            'qr_code_id' => $cpQr->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'scan_type' => Scan::TYPE_CROSS_PROMO,
            'scanned_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/scans?cross_promo_only=1&page_size=50')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    ->has('scans.data', 1)
                    ->where('scans.data.0.qr_code.type', 'cross_promo')
            );
    }

    public function test_business_id_filter_filters_saved_promotions(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $business1 = Business::factory()->create(['name' => 'Shop A']);
        $business2 = Business::factory()->create(['name' => 'Shop B']);

        $promo1 = Promotion::factory()->create(['business_id' => $business1->id, 'is_active' => true]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id, 'is_active' => true]);
        $qr1 = QRCode::factory()->promotion()->create(['business_id' => $business1->id, 'promotion_id' => $promo1->id]);
        $qr2 = QRCode::factory()->promotion()->create(['business_id' => $business2->id, 'promotion_id' => $promo2->id]);

        \App\Models\SavedQRCode::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr1->id,
            'saved_at' => now(),
        ]);
        \App\Models\SavedQRCode::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr2->id,
            'saved_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/scans?business_id=' . $business1->id)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    ->has('savedPromotions.data')
            );
    }
}

