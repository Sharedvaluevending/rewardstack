<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScanEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_expired_qr_shows_expired_error(): void
    {
        $qr = QRCode::factory()->create([
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This QR code has expired')
            );
    }

    public function test_scan_static_qr_with_missing_destination_shows_error(): void
    {
        $qr = QRCode::factory()->create([
            'type' => 'static',
            'destination_url' => null,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This QR code does not have a destination URL configured.')
            );
    }

    public function test_scan_internal_leaderboard_prize_qr_is_blocked_for_guests(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'intended_use' => QRCode::INTENDED_USE_LEADERBOARD_PRIZE,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/ScanError')
                    ->where('message', 'This QR code is an internal leaderboard prize and should not be scanned. Ask the business for the game QR code instead.')
            );
    }
}

