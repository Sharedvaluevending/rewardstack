<?php

namespace Tests\Feature\Console;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Redemption;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_command_can_seed_small_dataset_safely(): void
    {
        $business = Business::factory()->create();
        $business->owner->update(['role' => 'business']);

        // SeedDemoData requires at least 1 game to attach to QR codes.
        $games = Game::factory()->count(2)->create();

        // Ensure we have a few users to attribute activity to (business owner is already a user).
        User::factory()->count(3)->create();

        // Avoid sqlite enum/check-constraint issues around expanded win modes by providing
        // pre-existing QR codes and keeping --qr_codes=0 (so SeedDemoData won't insert
        // a 'leaderboard' win_mode into qr_code_games on sqlite).
        $qrcade = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade',
            'is_active' => true,
            'game_enabled' => true,
        ]);
        QRCodeGame::create([
            'qr_code_id' => $qrcade->id,
            'game_id' => $games->first()->id,
            'business_id' => $business->id,
            'is_active' => true,
            'promotion_id' => null,
            'win_mode' => QRCodeGame::WIN_MODE_ALWAYS,
        ]);

        $leaderboardQr = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'qrcade_leaderboard',
            'is_active' => true,
            'game_enabled' => true,
        ]);
        QRCodeGame::create([
            'qr_code_id' => $leaderboardQr->id,
            'game_id' => $games->first()->id,
            'business_id' => $business->id,
            'is_active' => true,
            'promotion_id' => null,
            'win_mode' => QRCodeGame::WIN_MODE_ALWAYS,
        ]);

        QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'promotion',
            'is_active' => true,
            'promotion_id' => null,
            'game_enabled' => false,
        ]);

        $this->artisan('demo:seed', [
            '--business_ids' => (string) $business->id,
            '--days' => '2',
            '--promotions' => '3',
            '--qr_codes' => '0',
            '--scans' => '8',
            '--plays' => '5',
            '--prizes' => '4',
            '--redemptions' => '3',
            '--saved' => '2',
            '--badges' => '2',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Promotion::query()->where('business_id', $business->id)->exists());
        $this->assertTrue(QRCode::query()->where('business_id', $business->id)->exists());
        $this->assertTrue(Scan::query()->where('business_id', $business->id)->exists());

        // Game sessions/plays can be 0 if randomization skips (e.g. missing QRCodeGame),
        // but with a seeded leaderboard QR they should generally exist.
        $this->assertTrue(GameSession::query()->where('business_id', $business->id)->exists());
        $this->assertTrue(GamePlay::query()->where('business_id', $business->id)->exists());

        $this->assertTrue(UserPromoToken::query()->where('business_id', $business->id)->exists());
        $this->assertTrue(Redemption::query()->where('business_id', $business->id)->exists());
    }
}

