<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Game;
use App\Models\Leaderboard;
use App\Models\PunchCard;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Scan;
use App\Models\SavedQRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScanControllerAdditionalCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_route_normalizes_up_code_and_redirects(): void
    {
        // "UPABCD1234" should normalize to "UP-ABCD-1234"
        $resp = $this->get('/promo/UPABCD1234');
        $resp->assertStatus(302);
        $resp->assertRedirect('/promo/UP-ABCD-1234');
    }

    public function test_promo_route_redirects_to_play_for_qrcade_qr(): void
    {
        $qr = QRCode::factory()->create([
            'code' => 'QRCAD123',
            'type' => 'qrcade',
            'destination_url' => null,
            'is_active' => true,
        ]);

        $this->get('/promo/' . $qr->code)
            ->assertStatus(302)
            ->assertRedirect('/play/' . $qr->code);
    }

    public function test_scan_qrcade_leaderboard_with_active_leaderboard_redirects_to_game_select(): void
    {
        $business = Business::factory()->create();
        $game = Game::factory()->create(['is_active' => true]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'LBRD1234',
            'type' => 'qrcade_leaderboard',
            'destination_url' => null,
            'is_active' => true,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        Leaderboard::factory()->create([
            'type' => Leaderboard::TYPE_LOCATION,
            'business_id' => $business->id,
            'game_id' => null,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(302)
            ->assertRedirect('/play/' . $qr->code);
    }

    public function test_scan_qrcade_leaderboard_without_leaderboard_redirects_directly_to_game(): void
    {
        $business = Business::factory()->create();
        $game = Game::factory()->create(['is_active' => true, 'slug' => 'test-game']);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'FAST1234',
            'type' => 'qrcade_leaderboard',
            'destination_url' => null,
            'is_active' => true,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(302)
            ->assertRedirect('/play/' . $qr->code . '/game/' . $game->slug);
    }

    public function test_promo_route_uses_cross_promo_view_and_handles_not_active_yet(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();

        $p1 = Promotion::factory()->create([
            'business_id' => $b1->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $p2 = Promotion::factory()->create([
            'business_id' => $b2->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_PENDING, // not accepted
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $b1->id,
            'code' => 'CROSS123',
            'type' => 'cross_promo',
            'destination_url' => null,
            'is_active' => true,
            'cross_promotion_id' => $cross->id,
        ]);

        $this->get('/promo/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/ScanError')
                ->where('message', 'This cross-promotion is not active yet.')
            );
    }

    public function test_promo_route_resolves_user_promo_token_code_and_displays_customer_qr_code(): void
    {
        $business = Business::factory()->create();
        $tokenOwner = User::factory()->create(['role' => 'customer']);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'PROMOAAA',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $tokenOwner->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-ABCD-EFGH',
            'qr_image_path' => 'qrcodes/personal.png',
        ]);

        $this->get('/promo/' . $token->code)
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/Promotion')
                ->where('qrCode.code', $token->code)
                ->where('qrCode.is_customer_promo', true)
                ->where('qrCode.image_url', $token->qr_image_url)
            );
    }

    public function test_promo_route_returns_scan_error_when_personal_code_not_found(): void
    {
        $this->get('/promo/UP-ZZZZ-ZZZZ')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/ScanError')
                ->where('message', 'Promotion not found. Your personal deal code could not be located.')
            );
    }

    public function test_promo_route_as_guest_sets_pending_promo_code_in_session(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'PEND1234',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        $this->get('/promo/' . $qr->code)->assertStatus(200);
        $this->assertSame($qr->code, session('pending_promo_code'));
        $this->assertNotNull(session('pending_promo_set_at'));
    }

    public function test_claim_cross_promo_offer_redirects_to_login_when_guest(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $resp = $this->post('/cross-promo/' . $cross->id . '/claim/' . $p1->id);
        $resp->assertStatus(302);
        $this->assertStringContainsString('/login', $resp->headers->get('Location') ?? '');
    }

    public function test_claim_cross_promo_offer_aborts_when_promotion_not_in_deal(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = User::factory()->create(['role' => 'customer']);
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $other = Promotion::factory()->create();

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/cross-promo/' . $cross->id . '/claim/' . $other->id)
            ->assertStatus(404);
    }

    public function test_claim_cross_promo_offer_returns_error_when_locked_in_sequential_chain(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = User::factory()->create(['role' => 'customer']);
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();

        $primary = Promotion::factory()->create([
            'business_id' => $b1->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $secondary = Promotion::factory()->create([
            'business_id' => $b2->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $primary->id,
            'promotion_2_id' => $secondary->id,
            'primary_promotion_id' => $primary->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        // Ensure there is a QR code associated so we don't fail earlier.
        QRCode::factory()->create([
            'business_id' => $b1->id,
            'type' => 'cross_promo',
            'destination_url' => null,
            'is_active' => true,
            'cross_promotion_id' => $cross->id,
        ]);

        $this->actingAs($user)
            ->from('/portal/partner-deals')
            ->post('/cross-promo/' . $cross->id . '/claim/' . $secondary->id)
            ->assertStatus(302)
            ->assertSessionHas('error', 'This offer is locked! Redeem the other offer first.');
    }

    public function test_claim_cross_promo_offer_returns_error_when_no_qr_code_set_up(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $user = User::factory()->create(['role' => 'customer']);
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_OPEN,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from('/portal/partner-deals')
            ->post('/cross-promo/' . $cross->id . '/claim/' . $p1->id)
            ->assertStatus(302)
            ->assertSessionHas('error');
    }

    public function test_claim_cross_promo_offer_creates_token_and_redirects_to_promo(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/token.png');
        });

        $user = User::factory()->create(['role' => 'customer']);
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create([
            'business_id' => $b1->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $p2 = Promotion::factory()->create([
            'business_id' => $b2->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cross = CrossPromotion::create([
            'code' => 'CP-' . uniqid(),
            'name' => 'Cross',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'primary_promotion_id' => $p1->id,
            'display_mode' => CrossPromotion::DISPLAY_SPLIT,
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $b1->id,
            'type' => 'cross_promo',
            'destination_url' => null,
            'is_active' => true,
            'cross_promotion_id' => $cross->id,
        ]);

        $resp = $this->actingAs($user)
            ->post('/cross-promo/' . $cross->id . '/claim/' . $p1->id);

        $resp->assertStatus(302);
        $location = (string) ($resp->headers->get('Location') ?? '');
        $this->assertStringContainsString('/promo/UP-', $location);

        $token = UserPromoToken::query()
            ->where('user_id', $user->id)
            ->where('promotion_id', $p1->id)
            ->first();

        $this->assertNotNull($token);
        $this->assertSame($qr->id, (int) $token->qr_code_id);
        $this->assertSame('qrcodes/token.png', $token->qr_image_path);
    }

    public function test_update_scan_geo_returns_404_when_qr_code_not_found(): void
    {
        $resp = $this->getJson('/api/public/scan/geo?code=NOPE1234&lat=1&lng=2');
        $resp->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_update_scan_geo_returns_404_when_no_recent_scan_found(): void
    {
        $qr = QRCode::factory()->create([
            'code' => 'GEO12345',
            'is_active' => true,
        ]);

        // Ensure no Scan rows exist in the last 10 minutes.
        $resp = $this->getJson('/api/public/scan/geo?code=' . $qr->code . '&lat=1&lng=2');
        $resp->assertStatus(404)->assertJson(['success' => false, 'error' => 'No recent scan found']);
    }

    public function test_update_scan_geo_falls_back_to_ip_match_when_session_differs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $qr = QRCode::factory()->create([
            'code' => 'IPFB1234',
            'is_active' => true,
        ]);

        $scan = Scan::create([
            'qr_code_id' => $qr->id,
            'business_id' => $qr->business_id,
            'user_id' => null,
            'scan_type' => Scan::TYPE_INFO,
            'session_id' => 'different-session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Linux',
            'scanned_at' => now(),
        ]);

        $this->getJson('/api/public/scan/geo?code=' . $qr->code . '&lat=12.34&lng=56.78&city=Testville')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $scan->refresh();
        $this->assertEqualsWithDelta(12.34, (float) $scan->latitude, 0.001);
        $this->assertEqualsWithDelta(56.78, (float) $scan->longitude, 0.001);
        $this->assertSame('Testville', $scan->city);
    }

    public function test_promo_route_deletes_saved_qr_when_promotion_unavailable(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/promo-token.png');
        });

        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => false, // unavailable
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'SAVED999',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        SavedQRCode::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'saved_at' => now(),
        ]);

        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);

        $this->assertDatabaseMissing('saved_qr_codes', [
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
        ]);
    }

    public function test_scan_static_as_logged_in_user_refreshes_existing_scan_without_incrementing_stats(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $qr = QRCode::factory()->create([
            'code' => 'STAT1234',
            'type' => 'static',
            'destination_url' => 'https://example.com/a',
            'is_active' => true,
            'total_scans' => 0,
            'unique_scans' => 0,
        ]);

        $this->actingAs($user)->get('/s/' . $qr->code)
            ->assertStatus(302)
            ->assertRedirect('https://example.com/a');

        $qr->refresh();
        $this->assertSame(1, (int) $qr->total_scans);

        $this->actingAs($user)->get('/s/' . $qr->code)
            ->assertStatus(302)
            ->assertRedirect('https://example.com/a');

        $qr->refresh();
        $this->assertSame(1, (int) $qr->total_scans, 'Should not double-increment when scan row is refreshed');
    }

    public function test_promo_page_includes_punch_card_progress_for_logged_in_customer(): void
    {
        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/promo-token.png');
        });

        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'punches_required' => 5,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'PUNCH123',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        $card = PunchCard::create([
            'promotion_id' => $promo->id,
            'user_id' => $customer->id,
            'customer_identifier' => 'cust-1',
            'punches' => 3,
            'completed_cards' => 1,
            'last_punch_at' => now()->subDay(),
        ]);

        $this->actingAs($customer)
            ->get('/promo/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/Promotion')
                ->where('punchCardProgress.total_required', 5)
                ->where('punchCardProgress.current_punches', 3)
                ->where('punchCardProgress.completed_cards', 1)
                ->where('punchCardProgress.has_identifier', true)
                ->where('punchCardProgress.punch_card_id', $card->id)
            );
    }

    public function test_promo_page_records_scan_for_logged_in_customer_when_no_recent_scan(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/promo-token.png');
        });

        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'PROMO123',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);

        $this->assertSame(1, Scan::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
    }

    public function test_promo_page_does_not_double_record_scan_within_two_minutes(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/promo-token.png');
        });

        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));

        $customer = User::factory()->create(['role' => 'customer']);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'PROMO456',
            'type' => 'promotion',
            'destination_url' => null,
            'is_active' => true,
            'promotion_id' => $promo->id,
        ]);

        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);
        $this->assertSame(1, Scan::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());

        Carbon::setTestNow(Carbon::parse('2026-01-25 12:01:00'));
        $this->actingAs($customer)->get('/promo/' . $qr->code)->assertStatus(200);
        $this->assertSame(1, Scan::query()->where('user_id', $customer->id)->where('qr_code_id', $qr->id)->count());
    }
}

