<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Game;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_play_index_redirects_to_promotion_when_qr_code_has_no_games(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'promotion',
            'game_enabled' => false,
        ]);

        $response = $this->get(route('play.index', ['code' => $qrCode->code]));

        $response->assertRedirect(route('promotion.show', $qrCode->code));
    }

    public function test_play_index_returns_200_with_game_select_when_multiple_games(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $game1 = Game::factory()->create(['is_active' => true, 'slug' => 'game-one']);
        $game2 = Game::factory()->create(['is_active' => true, 'slug' => 'game-two']);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
        ]);
        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game1->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);
        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game2->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);

        $response = $this->get(route('play.index', ['code' => $qrCode->code]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Play/GameSelect')
                ->has('games', 2)
                ->has('qrCode')
                ->where('qrCode.code', $qrCode->code));
    }

    public function test_play_index_redirects_to_single_game_when_only_one_game(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $game = Game::factory()->create(['is_active' => true, 'slug' => 'solo-game']);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
        ]);
        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);

        $response = $this->get(route('play.index', ['code' => $qrCode->code]));

        $response->assertRedirect('/play/' . $qrCode->code . '/game/' . $game->slug);
    }

    public function test_play_game_returns_200_with_valid_code_and_game(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $game = Game::factory()->create(['is_active' => true, 'slug' => 'play-me']);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
        ]);
        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);

        $response = $this->get(route('play.game', ['code' => $qrCode->code, 'game' => $game]));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Play/Game')
                ->has('game')
                ->has('qrCode')
                ->where('qrCode.code', $qrCode->code)
                ->where('game.slug', 'play-me'));
    }

    public function test_play_index_returns_404_for_invalid_code(): void
    {
        $response = $this->get(route('play.index', ['code' => 'nonexistent-code']));

        $response->assertStatus(404);
    }

    public function test_play_game_returns_404_for_invalid_game(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
        ]);

        $response = $this->get('/play/' . $qrCode->code . '/game/nonexistent-game-slug');

        $response->assertStatus(404);
    }

}
