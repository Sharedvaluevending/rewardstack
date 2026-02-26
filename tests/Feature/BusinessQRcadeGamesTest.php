<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessGame;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessQRcadeGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_qrcade_games_page_loads_and_can_toggle_game(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'pro',
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $game = Game::factory()->create([
            'tier' => Game::TIER_BASIC,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->get('/business/qrcade/games')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Business/QRcade/Games')
                    ->has('allGames')
                    ->has('businessGames')
            );

        $this->actingAs($owner)
            ->post("/business/qrcade/games/{$game->slug}/toggle")
            ->assertStatus(302)
            ->assertRedirect('/business/qrcade/games');

        $this->assertDatabaseHas('business_games', [
            'business_id' => $business->id,
            'game_id' => $game->id,
            'is_enabled' => 1,
        ]);

        // Toggle off
        $this->actingAs($owner)
            ->post("/business/qrcade/games/{$game->slug}/toggle")
            ->assertStatus(302);

        $bg = BusinessGame::where('business_id', $business->id)->where('game_id', $game->id)->first();
        $this->assertNotNull($bg);
        $this->assertFalse((bool) $bg->is_enabled);
    }
}

