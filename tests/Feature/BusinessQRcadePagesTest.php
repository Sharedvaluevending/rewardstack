<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessGame;
use App\Models\Game;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessQRcadePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_qrcade_pages_load_and_basic_updates_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'pro',
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $game = Game::factory()->create(['is_active' => true]);
        $businessGame = BusinessGame::create([
            'business_id' => $business->id,
            'game_id' => $game->id,
            'is_enabled' => true,
        ]);

        $promo = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $qrGame = QRCodeGame::create([
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'is_active' => true,
            'win_mode' => 'always',
        ]);

        $this->actingAs($owner)
            ->get('/business/qrcade')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/Index'));

        $this->actingAs($owner)
            ->get('/business/qrcade/how-to')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/HowTo'));

        $this->actingAs($owner)
            ->get('/business/qrcade/rewards')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/Rewards'));

        $this->actingAs($owner)
            ->get('/business/qrcade/schedule')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/Schedule'));

        $this->actingAs($owner)
            ->put("/business/qrcade/schedule/{$businessGame->id}", [
                'schedule' => [
                    'monday' => ['start' => '09:00', 'end' => '17:00'],
                ],
            ])
            ->assertStatus(302);

        $this->actingAs($owner)
            ->put("/business/qrcade/rewards/{$qrGame->id}", [
                'win_mode' => 'always',
                'promotion_id' => $promo->id,
            ])
            ->assertStatus(302);

        $this->actingAs($owner)
            ->get('/business/qrcade/leaderboards')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/Leaderboards'));

        $this->actingAs($owner)
            ->post('/business/qrcade/leaderboards', [
                'name' => 'My Leaderboard',
                'type' => 'location',
                'reset_frequency' => 'never',
                'is_active' => true,
            ])
            ->assertStatus(302);

        $lb = Leaderboard::where('business_id', $business->id)->first();
        $this->assertNotNull($lb);

        $this->actingAs($owner)
            ->get("/business/qrcade/leaderboards/{$lb->id}")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/LeaderboardShow'));

        $this->actingAs($owner)
            ->get("/business/qrcade/leaderboards/{$lb->id}/edit")
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/LeaderboardEdit'));

        $this->actingAs($owner)
            ->get('/business/qrcade/analytics')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRcade/Analytics'));
    }
}

