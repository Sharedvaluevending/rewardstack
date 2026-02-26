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

class PortalPartnerDealsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_partner_deals_lists_cross_promos_user_scanned(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $b1 = Business::factory()->create(['name' => 'Biz One']);
        $b2 = Business::factory()->create(['name' => 'Biz Two']);

        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30)]);

        $crossPromo = CrossPromotion::create([
            'code' => 'PORTAL01',
            'name' => 'Portal Deal',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $qr = QRCode::create([
            'business_id' => $b1->id,
            'code' => 'PRTL1234',
            'name' => 'Portal QR',
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'is_active' => true,
        ]);

        Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $b1->id,
            'user_id' => $customer->id,
            'scan_type' => Scan::TYPE_CROSS_PROMO,
            'session_id' => 'test-session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'scanned_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/partner-deals')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/PartnerDeals')
                    ->has('crossPromos', 1)
                    ->where('crossPromos.0.id', $crossPromo->id)
                    ->where('crossPromos.0.qr_code.code', $qr->code)
            );
    }
}

