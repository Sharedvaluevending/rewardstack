<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessGame;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_pages_load(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get('/portal/games')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Games'));

        $this->actingAs($customer)
            ->get('/portal/games/history')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/GamesHistory'));
    }

    public function test_nearby_requires_lat_lng(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get('/portal/games/nearby')->assertStatus(302);
    }

    public function test_nearby_returns_businesses_with_enabled_games(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $business = Business::factory()->create([
            'is_active' => true,
            'latitude' => 43.6532,
            'longitude' => -79.3832,
        ]);
        $game = Game::factory()->create(['is_active' => true]);

        BusinessGame::create([
            'business_id' => $business->id,
            'game_id' => $game->id,
            'is_enabled' => true,
        ]);

        $this->actingAs($customer)
            ->get('/portal/games/nearby?latitude=43.6532&longitude=-79.3832&radius=1000')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/GamesNearby')
                    ->has('businesses', 1)
                    ->where('businesses.0.id', $business->id)
            );
    }

    public function test_games_history_includes_plays(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        GamePlay::factory()->count(3)->create(['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->get('/portal/games/history')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Portal/GamesHistory')
                    ->has('plays.data', 3)
            );
    }
}

