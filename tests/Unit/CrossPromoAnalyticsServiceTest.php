<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Services\CrossPromoAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_analytics_returns_structure_with_zero_counts_when_no_activity(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);

        $crossPromo = CrossPromotion::create([
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'name' => 'Test Cross Promo',
            'code' => 'CP-' . \Illuminate\Support\Str::random(8),
            'status' => 'accepted',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'display_mode' => 'side_by_side',
            'chain_mode' => 'parallel',
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $b1->id,
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
        ]);

        $service = new CrossPromoAnalyticsService();
        $analytics = $service->getAnalytics($crossPromo);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('overview', $analytics);
        $ov = $analytics['overview'];
        $this->assertArrayHasKey('total_scans', $ov);
        $this->assertArrayHasKey('total_claims', $ov);
        $this->assertArrayHasKey('total_redemptions', $ov);
        $this->assertArrayHasKey('scan_to_claim_rate', $ov);
        $this->assertArrayHasKey('claim_to_redemption_rate', $ov);
        $this->assertSame(0, $ov['total_scans']);
        $this->assertSame(0, $ov['total_claims']);
        $this->assertSame(0, $ov['total_redemptions']);
    }

    public function test_get_analytics_includes_scans_when_present(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);

        $crossPromo = CrossPromotion::create([
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'name' => 'Test Cross Promo',
            'code' => 'CP-' . \Illuminate\Support\Str::random(8),
            'status' => 'accepted',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'display_mode' => 'side_by_side',
            'chain_mode' => 'parallel',
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $b1->id,
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
        ]);

        Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $b1->id,
            'session_id' => 'sess-1',
            'scanned_at' => now(),
        ]);

        $service = new CrossPromoAnalyticsService();
        $analytics = $service->getAnalytics($crossPromo);

        $this->assertSame(1, $analytics['overview']['total_scans']);
    }
}
