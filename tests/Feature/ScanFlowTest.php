<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_special_legacy_code_redirects_to_home(): void
    {
        $this->get('/s/AaaAVH8b')
            ->assertStatus(302)
            ->assertRedirect(route('home'));
    }

    public function test_scan_missing_code_shows_scan_error_component(): void
    {
        $this->get('/s/doesnotexist')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'QR code not found')
            );
    }

    public function test_scan_inactive_code_shows_scan_error_component(): void
    {
        $qr = QRCode::factory()->promotion()->inactive()->create();

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This QR code is no longer active')
            );
    }

    public function test_scan_geo_update_endpoint_updates_latest_scan_for_session(): void
    {
        $qr = QRCode::factory()->promotion()->create([
            'is_active' => true,
        ]);

        // First hit records a scan row for this session
        $this->get('/s/' . $qr->code)->assertStatus(302);

        $scan = Scan::where('qr_code_id', $qr->id)->latest('scanned_at')->first();
        $this->assertNotNull($scan);
        $this->assertNull($scan->latitude);

        // Then the frontend can update that scan row with consent-based geo
        $this->get('/api/public/scan/geo?code=' . $qr->code . '&lat=40.7128&lng=-74.0060&city=New%20York&region=NY&country=United%20States')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $scan->refresh();
        $this->assertEquals(40.7128, (float) $scan->latitude);
        $this->assertEquals(-74.0060, (float) $scan->longitude);
        $this->assertEquals('New York', $scan->city);
        $this->assertEquals('NY', $scan->region);
        $this->assertEquals('United States', $scan->country);
    }

    public function test_promo_show_renders_for_valid_promotion_code(): void
    {
        $promotion = Promotion::factory()->create([
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $promotion->business_id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        $this->get('/promo/' . $qr->code)
            ->assertStatus(200);
    }

    public function test_promo_show_normalizes_customer_token_code(): void
    {
        $promotion = Promotion::factory()->create();
        $qr = QRCode::factory()->promotion()->create([
            'code' => 'UP-ABCD-1234',
            'business_id' => $promotion->business_id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        $this->get('/promo/up abcd 1234')
            ->assertRedirect(route('promotion.show', ['code' => 'UP-ABCD-1234']));
    }
}
