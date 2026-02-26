<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Game;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayControllerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_session_returns_json_with_session_token(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
            'code' => 'PLAY-' . uniqid(),
        ]);
        $game = Game::factory()->create(['slug' => 'test-game', 'is_active' => true]);
        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => 'random',
        ]);

        $response = $this->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class, \App\Http\Middleware\VerifyCsrfToken::class])
            ->postJson("/api/play/{$qrCode->code}/game/{$game->slug}/start", [
                'is_practice' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'session' => [
                    'token',
                    'expires_at',
                ],
            ]);
    }

    public function test_start_session_fails_for_invalid_code(): void
    {
        $game = Game::factory()->create(['slug' => 'test-game']);

        $response = $this->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class, \App\Http\Middleware\VerifyCsrfToken::class])
            ->postJson('/api/play/INVALID-CODE/game/' . $game->slug . '/start', []);

        $this->assertFalse($response->isSuccessful(), 'Invalid code should not succeed');
    }

    public function test_verify_location_returns_json_with_verified_and_requirements(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'LOC-' . uniqid(),
        ]);
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class, \App\Http\Middleware\VerifyCsrfToken::class])
            ->postJson('/api/game/verify-location', [
                'qr_code_id' => $qrCode->id,
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'verified',
                'requirements',
            ]);
    }
}
