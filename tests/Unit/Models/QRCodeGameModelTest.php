<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QRCodeGameModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_win_mode_constants_are_defined(): void
    {
        $this->assertSame('always', QRCodeGame::WIN_MODE_ALWAYS);
        $this->assertSame('score', QRCodeGame::WIN_MODE_SCORE);
        $this->assertSame('time', QRCodeGame::WIN_MODE_TIME);
        $this->assertSame('random', QRCodeGame::WIN_MODE_RANDOM);
        $this->assertSame('leaderboard', QRCodeGame::WIN_MODE_LEADERBOARD);
        $this->assertSame('skill', QRCodeGame::WIN_MODE_SKILL);
        $this->assertSame('tiered', QRCodeGame::WIN_MODE_TIERED);
    }

    public function test_is_available_now_returns_false_when_inactive(): void
    {
        $qrCode = QRCode::factory()->create();
        $game = Game::factory()->create();
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'is_active' => false,
        ]);
        $this->assertFalse($qrCodeGame->isAvailableNow());
    }

    public function test_is_available_now_returns_true_when_active_and_no_restrictions(): void
    {
        $qrCode = QRCode::factory()->create();
        $game = Game::factory()->create();
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'is_active' => true,
        ]);
        $this->assertTrue($qrCodeGame->isAvailableNow());
    }

    public function test_is_available_now_respects_active_days(): void
    {
        // Saturday - not in allowed days
        Carbon::setTestNow(Carbon::parse('2026-01-17 12:00:00', 'America/Toronto'));
        $qrCode = QRCode::factory()->create();
        $game = Game::factory()->create();
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'is_active' => true,
            'active_days' => ['wednesday', 'thursday'],
        ]);
        $this->assertSame('saturday', strtolower(now()->format('l')));
        $this->assertFalse($qrCodeGame->isAvailableNow());

        // Wednesday - in allowed days
        Carbon::setTestNow(Carbon::parse('2026-01-21 12:00:00', 'America/Toronto'));
        $this->assertSame('wednesday', strtolower(now()->format('l')));
        $this->assertTrue($qrCodeGame->fresh()->isAvailableNow());
    }

    public function test_relationships_exist(): void
    {
        $qrCode = QRCode::factory()->create();
        $game = Game::factory()->create();
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'is_active' => true,
        ]);
        $this->assertTrue($qrCodeGame->qrCode->is($qrCode));
        $this->assertTrue($qrCodeGame->game->is($game));
        $this->assertTrue($qrCodeGame->business->is($qrCode->business));
    }

    public function test_get_actual_total_wins_attribute_counts_wins(): void
    {
        $qrCode = QRCode::factory()->create();
        $game = Game::factory()->create();
        $qrCodeGame = QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'is_active' => true,
        ]);
        $session = \App\Models\GameSession::factory()->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
        ]);
        GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'result' => GamePlay::RESULT_WIN,
        ]);
        GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $qrCode->business_id,
            'result' => GamePlay::RESULT_LOSE,
        ]);
        $qrCodeGame->refresh();
        $this->assertSame(1, $qrCodeGame->actual_total_wins);
    }
}
