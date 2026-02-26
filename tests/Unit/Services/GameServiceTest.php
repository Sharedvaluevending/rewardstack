<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\User;
use App\Services\GameService;
use App\Services\LocationLockService;
use App\Services\PrizeService;
use App\Services\QRGeneratorService;
use App\Services\CustomerCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class GameServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameService $gameService;
    protected LocationLockService $locationService;
    protected PrizeService $prizeService;
    protected QRGeneratorService $qrGenerator;
    protected CustomerCodeService $customerCodeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->locationService = Mockery::mock(LocationLockService::class);
        $this->prizeService = Mockery::mock(PrizeService::class);
        $this->qrGenerator = Mockery::mock(QRGeneratorService::class);
        $this->customerCodeService = Mockery::mock(CustomerCodeService::class);
        $this->gameService = new GameService($this->locationService, $this->prizeService, $this->qrGenerator, $this->customerCodeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_start_a_game_session()
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->locationService->shouldReceive('verify')
            ->andReturn(true);

        $session = $this->gameService->startSession(
            $qrCode,
            $game,
            $user,
            ['latitude' => 40.7128, 'longitude' => -74.0060],
            ['ip' => '127.0.0.1', 'user_agent' => 'Test Agent']
        );

        $this->assertInstanceOf(GameSession::class, $session);
        $this->assertEquals($qrCode->id, $session->qr_code_id);
        $this->assertEquals($game->id, $session->game_id);
        $this->assertEquals($user->id, $session->user_id);
        $this->assertEquals(GameSession::STATUS_ACTIVE, $session->status);
        $this->assertNotNull($session->session_token);
    }

    /** @test */
    public function it_can_start_playing_a_game()
    {
        $session = GameSession::factory()->create();

        $gamePlay = $this->gameService->startPlaying($session);

        $this->assertInstanceOf(GamePlay::class, $gamePlay);
        $this->assertEquals($session->id, $gamePlay->game_session_id);
        $this->assertEquals(GameSession::STATUS_PLAYING, $session->fresh()->status);
        $this->assertNotNull($gamePlay->started_at);
    }

    /** @test */
    public function it_calculates_score_correctly()
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['max_score' => 1000]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        $qrCodeGame = QRCodeGame::factory()->scoreBased(500)->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'promotion_id' => null,
        ]);

        $this->prizeService->shouldReceive('createReward')
            ->andReturn(null);

        $result = $this->gameService->submitScore($gamePlay, 750, []);

        $this->assertEquals(750, $result['score']);
        $this->assertEquals(GamePlay::RESULT_WIN, $result['result']);
        $this->assertTrue($result['won']);
        $this->assertEquals(GameSession::STATUS_COMPLETED, $session->fresh()->status);
    }

    /** @test */
    public function it_detects_suspicious_high_scores()
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['max_score' => 1000]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
        ]);

        $result = $this->gameService->submitScore($gamePlay, 1500, []);

        $this->assertTrue($gamePlay->fresh()->is_suspicious);
        $this->assertEquals('Score exceeds maximum possible', $gamePlay->fresh()->suspicious_reason);
    }

    /** @test */
    public function it_detects_impossibly_fast_completion()
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['time_limit' => 60]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSecond(), // Completed in 1 second
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
        ]);

        $result = $this->gameService->submitScore($gamePlay, 100, []);

        $this->assertTrue($gamePlay->fresh()->is_suspicious);
        $this->assertEquals('Game completed impossibly fast', $gamePlay->fresh()->suspicious_reason);
    }

    /** @test */
    public function it_detects_rapid_replays()
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        
        // Create 11 recent plays
        GamePlay::factory()->count(11)->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'created_at' => now()->subMinutes(3),
        ]);

        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
        ]);

        $result = $this->gameService->submitScore($gamePlay, 100, []);

        $this->assertTrue($gamePlay->fresh()->is_suspicious);
        $this->assertEquals('Too many plays in short period', $gamePlay->fresh()->suspicious_reason);
    }

    /** @test */
    public function it_validates_daily_puzzle_submission()
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['type' => Game::TYPE_WORD_SEARCH]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        // Create a win today
        GamePlay::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'result' => GamePlay::RESULT_WIN,
            'created_at' => now()->startOfDay()->addHour(),
        ]);

        $validation = $this->gameService->validateDailyPuzzle($gamePlay, []);

        $this->assertFalse($validation['valid']);
        $this->assertEquals('Already completed today\'s puzzle', $validation['reason']);
    }

    /** @test */
    public function it_updates_user_stats_on_score_submission()
    {
        $user = User::factory()->create([
            'total_games_played' => 0,
            'lifetime_score' => 0,
            'highest_score' => 0,
        ]);
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
            'score' => 0,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);

        QRCodeGame::factory()->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
        ]);

        $this->prizeService->shouldReceive('createReward')
            ->andReturn(null);

        $this->gameService->submitScore($gamePlay, 500, []);

        $user->refresh();
        $this->assertEquals(1, $user->total_games_played);
        $this->assertEquals(500, $user->lifetime_score);
        $this->assertEquals(500, $user->highest_score);
    }

    /** @test */
    public function it_returns_fun_only_false_for_guest_when_prizes_exist(): void
    {
        $business = Business::factory()->create();
        $promo = \App\Models\Promotion::factory()->create(['business_id' => $business->id]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $game = Game::factory()->create();

        QRCodeGame::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
            'promotion_id' => $promo->id,
        ]);

        $res = $this->gameService->getFunOnlyStatus($qr, $game, null);
        $this->assertFalse($res['fun_only']);
        $this->assertNull($res['reason']);
    }

    /** @test */
    public function it_starts_session_with_location_none_skips_verify(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        \Illuminate\Support\Facades\DB::table('qr_codes')->where('id', $qrCode->id)->update(['location_lock_type' => 'none']);
        $qrCode->refresh();
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->locationService->shouldNotReceive('verify');

        $session = $this->gameService->startSession($qrCode, $game, $user, [], []);

        $this->assertInstanceOf(GameSession::class, $session);
        $this->assertEquals(GameSession::LOCATION_VERIFIED, $session->fresh()->location_status);
    }

    /** @test */
    public function it_verifies_session_location_when_required(): void
    {
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $qrCode->update(['location_lock_type' => 'gps']);
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->locationService->shouldReceive('verify')
            ->once()
            ->with(\Mockery::type(QRCode::class), ['latitude' => 40.0, 'longitude' => -74.0])
            ->andReturn(true);

        $session = $this->gameService->startSession($qrCode, $game, $user, [
            'latitude' => 40.0,
            'longitude' => -74.0,
        ], []);

        $this->assertEquals(GameSession::LOCATION_VERIFIED, $session->fresh()->location_status);
    }

    /** @test */
    public function it_returns_idempotent_result_when_play_already_completed(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
            'score' => 500,
            'result' => GamePlay::RESULT_WIN,
            'game_data' => ['_user_promo_token_code' => 'UP-1234-5678'],
        ]);

        $result = $this->gameService->submitScore($gamePlay, 999, []);

        $this->assertEquals(500, $result['score']);
        $this->assertTrue($result['won']);
        $this->assertEquals('UP-1234-5678', $result['user_promo_token']['code'] ?? null);
    }

    /** @test */
    public function it_caps_score_at_absolute_ceiling(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['max_score' => null]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
        ]);

        QRCodeGame::factory()->create(['qr_code_id' => $qrCode->id, 'game_id' => $game->id]);
        $this->prizeService->shouldReceive('createReward')->andReturn(null);

        $result = $this->gameService->submitScore($gamePlay, 2000000, []);

        $this->assertEquals(999999, $result['score']);
        $this->assertEquals(999999, $gamePlay->fresh()->score);
    }

    /** @test */
    public function it_returns_expired_when_score_submitted_after_time_limit(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['time_limit' => 30]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subMinutes(2),
            'completed_at' => null,
        ]);

        QRCodeGame::factory()->create(['qr_code_id' => $qrCode->id, 'game_id' => $game->id]);

        $result = $this->gameService->submitScore($gamePlay, 500, []);

        $this->assertTrue($result['expired'] ?? false);
        $this->assertEquals(0, $result['score']);
        $this->assertTrue($gamePlay->fresh()->is_suspicious);
    }

    /** @test */
    public function it_returns_practice_result_when_is_practice(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_practice' => true,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'is_practice' => true,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
        ]);

        QRCodeGame::factory()->create(['qr_code_id' => $qrCode->id, 'game_id' => $game->id]);

        $result = $this->gameService->submitScore($gamePlay, 500, []);

        $this->assertTrue($result['is_practice'] ?? false);
        $this->assertEquals(500, $result['score']);
        $this->assertFalse($result['won']);
        $this->assertEmpty($result['badges_earned']);
    }

    /** @test */
    public function it_detects_negative_score_as_suspicious(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
        ]);

        QRCodeGame::factory()->create(['qr_code_id' => $qrCode->id, 'game_id' => $game->id]);

        $result = $this->gameService->submitScore($gamePlay, -100, []);

        $this->assertTrue($gamePlay->fresh()->is_suspicious);
        $this->assertEquals('Negative score submitted', $gamePlay->fresh()->suspicious_reason);
    }

    /** @test */
    public function it_has_completed_daily_puzzle_returns_can_play_for_non_daily_game(): void
    {
        $game = Game::factory()->create(['type' => Game::TYPE_SNAKE]);
        $service = app(GameService::class);
        $res = $service->hasCompletedDailyPuzzle(null, $game, 1);

        $this->assertFalse($res['is_daily_puzzle']);
        $this->assertTrue($res['can_play']);
    }

    /** @test */
    public function it_has_completed_daily_puzzle_returns_completed_today_when_user_won(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $game = Game::factory()->create(['type' => Game::TYPE_WORD_SEARCH]);
        GamePlay::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'result' => GamePlay::RESULT_WIN,
            'created_at' => now()->startOfDay()->addHour(),
        ]);

        $service = app(GameService::class);
        $res = $service->hasCompletedDailyPuzzle($user, $game, $business->id);

        $this->assertTrue($res['is_daily_puzzle']);
        $this->assertTrue($res['completed_today']);
        $this->assertFalse($res['can_play']);
    }

    /** @test */
    public function it_validates_daily_puzzle_valid_seed(): void
    {
        $game = Game::factory()->create(['type' => Game::TYPE_WORD_SEARCH]);
        $session = GameSession::factory()->create();
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'game_id' => $game->id,
        ]);

        $service = app(GameService::class);
        $res = $service->validateDailyPuzzle($gamePlay, [
            'dailySeed' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($res['valid']);
    }

    /** @test */
    public function it_validates_daily_puzzle_invalid_seed(): void
    {
        $game = Game::factory()->create(['type' => Game::TYPE_WORD_SEARCH]);
        $session = GameSession::factory()->create();
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'game_id' => $game->id,
        ]);

        $service = app(GameService::class);
        $res = $service->validateDailyPuzzle($gamePlay, ['dailySeed' => '2020-01-01']);

        $this->assertFalse($res['valid']);
        $this->assertEquals('Invalid puzzle date', $res['reason']);
    }

    /** @test */
    public function it_associates_anonymous_game_plays_with_user(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $session = GameSession::factory()->create([
            'user_id' => null,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => null,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
        ]);

        session()->put('anonymous_game_plays', [
            [
                'game_play_id' => $gamePlay->id,
                'game_session_id' => $session->id,
                'business_id' => $business->id,
                'game_id' => $game->id,
                'qr_code_id' => $qrCode->id,
                'score' => 100,
                'result' => GamePlay::RESULT_COMPLETE,
                'reward_tier' => null,
                'completed_at' => now(),
                'has_reward' => false,
                'reward_id' => null,
            ],
        ]);

        $service = app(GameService::class);
        $count = $service->associateAnonymousGamePlays($user);

        $this->assertEquals(1, $count);
        $this->assertEquals($user->id, $gamePlay->fresh()->user_id);
        $this->assertEmpty(session()->get('anonymous_game_plays', []));
    }

    /** @test */
    public function it_gets_business_game_stats(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        GamePlay::factory()->count(3)->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'score' => 100,
            'result' => GamePlay::RESULT_COMPLETE,
        ]);
        GamePlay::factory()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'score' => 200,
            'result' => GamePlay::RESULT_WIN,
        ]);

        $service = app(GameService::class);
        $stats = $service->getBusinessGameStats($business->id, 30);

        $this->assertEquals(4, $stats['total_plays']);
        $this->assertEquals(1, $stats['unique_players']);
        $this->assertEquals(1, $stats['total_wins']);
    }

    /** @test */
    public function it_awards_prize_when_won(): void
    {
        $promo = \App\Models\Promotion::factory()->create();
        $gamePlay = GamePlay::factory()->create(['result' => GamePlay::RESULT_WIN]);
        $result = ['won' => true, 'promotion_id' => $promo->id, 'tier' => null];

        $reward = \App\Models\GameReward::factory()->create();
        $this->prizeService->shouldReceive('createReward')
            ->once()
            ->with(\Mockery::type(GamePlay::class), $promo->id, null)
            ->andReturn($reward);

        $awarded = $this->gameService->awardPrize($gamePlay, $result);
        $this->assertSame($reward, $awarded);
    }

    /** @test */
    public function it_award_prize_returns_null_when_not_won(): void
    {
        $gamePlay = GamePlay::factory()->create();
        $result = ['won' => false, 'promotion_id' => null];

        $awarded = $this->gameService->awardPrize($gamePlay, $result);
        $this->assertNull($awarded);
    }

    /** @test */
    public function it_submits_score_with_leaderboard_win_mode(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create();
        $leaderboard = \App\Models\Leaderboard::factory()->create([
            'business_id' => $business->id,
            'game_id' => $game->id,
            'type' => \App\Models\Leaderboard::TYPE_GAME_SPECIFIC,
            'is_active' => true,
        ]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
        ]);

        QRCodeGame::create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_LEADERBOARD,
            'leaderboard_id' => $leaderboard->id,
        ]);

        $result = $this->gameService->submitScore($gamePlay, 500, []);

        $this->assertFalse($result['won']);
        $this->assertArrayHasKey('leaderboard', $result);
        $this->assertEquals(GamePlay::RESULT_COMPLETE, $result['result']);
    }

    /** @test */
    public function it_normalizes_snake_score_from_game_data_when_score_zero(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $game = Game::factory()->create(['type' => Game::TYPE_SNAKE]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'started_at' => now()->subSeconds(30),
            'completed_at' => null,
        ]);

        QRCodeGame::factory()->scoreBased(100)->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
        ]);
        $this->prizeService->shouldReceive('createReward')->andReturn(null);

        $result = $this->gameService->submitScore($gamePlay, 0, [
            'foodEaten' => 5,
            'length' => 6,
        ]);

        $expectedScore = (5 * 5) + (11 * 5);
        $this->assertEquals($expectedScore, $result['score']);
    }

    /** @test */
    public function it_check_achievements_returns_empty_when_no_user(): void
    {
        $gamePlay = GamePlay::factory()->create(['user_id' => null]);
        $badges = $this->gameService->checkAchievements($gamePlay);
        $this->assertEmpty($badges);
    }

    /** @test */
    public function it_check_achievements_returns_array(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $game = Game::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $session = GameSession::factory()->create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'score' => 1000,
            'result' => GamePlay::RESULT_WIN,
        ]);

        $badges = $this->gameService->checkAchievements($gamePlay);
        $this->assertIsArray($badges);
    }

    /** @test */
    public function it_create_leaderboard_prize_reward_creates_reward(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $promo = \App\Models\Promotion::factory()->create(['business_id' => $business->id]);
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $leaderboard = \App\Models\Leaderboard::factory()->create([
            'business_id' => $business->id,
            'game_id' => $game->id,
            'qr_code_id' => $qrCode->id,
        ]);
        $entry = \App\Models\LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $user->id,
            'business_id' => $business->id,
            'period_key' => $leaderboard->getCurrentPeriodKey(),
            'score' => 500,
            'games_played' => 1,
        ]);

        $reward = \App\Models\GameReward::factory()->create();
        $this->prizeService->shouldReceive('createReward')
            ->once()
            ->andReturn($reward);

        $result = $this->gameService->createLeaderboardPrizeReward($entry, $promo);
        $this->assertSame($reward, $result);
    }
}

