<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessAnalyticsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_pages_load_for_business(): void
    {
        Cache::flush();

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $promo = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        $qr = QRCode::factory()->promotion()->create(['business_id' => $business->id, 'promotion_id' => $promo->id, 'is_active' => true]);

        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'session_id' => 'sess-1',
            'scan_type' => Scan::TYPE_PROMOTION,
            'scanned_at' => now()->subDay(),
        ]);

        Redemption::create([
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'scan_id' => $scan->id,
            'customer_identifier' => 'CUST1',
            'discount_amount' => 5.00,
            'original_amount' => 20.00,
            'final_amount' => 15.00,
            'redeemed_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->get('/business/analytics?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Index'));

        $this->actingAs($owner)
            ->get('/business/analytics/finance?period=30')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Analytics/Finance'));
    }
}

