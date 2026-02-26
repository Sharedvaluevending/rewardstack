<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCrossPromoEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_cross_promo_missing_cross_promo_shows_error(): void
    {
        $b = Business::factory()->create();
        $qr = QRCode::create([
            'business_id' => $b->id,
            'code' => 'CPMISS01',
            'name' => 'Broken Cross Promo',
            'type' => 'cross_promo',
            'cross_promotion_id' => null,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This partner deal is not configured for this QR code (it may have been removed).')
            );
    }

    public function test_scan_cross_promo_not_fully_set_up_shows_error(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'CPHALF01',
            'name' => 'Half setup',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => null,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $qr = QRCode::create([
            'business_id' => $b1->id,
            'code' => 'CPHALFQR',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'Cross-promotion offers are not fully set up yet.')
            );
    }

    public function test_scan_cross_promo_usage_limit_reached_shows_error(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'CPLIMIT1',
            'name' => 'Limited',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'usage_limit' => 1,
        ]);

        $qr = QRCode::create([
            'business_id' => $b1->id,
            'code' => 'CPLIMQR1',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        $u = User::factory()->create(['role' => 'customer']);
        UserPromoToken::create([
            'user_id' => $u->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $p1->id,
            'business_id' => $b1->id,
            'code' => 'UP-AAAA-BBBB',
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This cross-promotion has reached its usage limit.')
            );
    }
}

