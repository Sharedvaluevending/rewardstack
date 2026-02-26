<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCrossPromoStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_or_inactive_cross_promo_scan_shows_not_active_yet_error(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $cp = CrossPromotion::create([
            'code' => 'CPSTAT01',
            'name' => 'Pending',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        $qr = QRCode::create([
            'business_id' => $b1->id,
            'code' => 'CPSTATQR',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $cp->id,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This cross-promotion is not active yet.')
            );
    }

    public function test_expired_or_not_started_cross_promo_scan_shows_currently_active_error(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10)]);

        $cp = CrossPromotion::create([
            'code' => 'CPSTAT02',
            'name' => 'Expired',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $qr = QRCode::create([
            'business_id' => $b1->id,
            'code' => 'CPSTATQ2',
            'name' => 'Cross Promo QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $cp->id,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This cross-promotion is not currently active.')
            );
    }
}

