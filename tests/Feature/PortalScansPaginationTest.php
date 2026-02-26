<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalScansPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_scans_page_returns_paginated_scans(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $business = Business::factory()->create();

        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
        ]);

        Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'user_id' => $customer->id,
            'scan_type' => \App\Models\Scan::TYPE_PROMOTION,
            'scanned_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get('/portal/scans')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/Scans')
                    ->has('scans.data', 1)
                    ->has('scans.links')
            );
    }
}

