<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\Leaderboard;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaderboardCompetitionTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $gameService;
    protected $game;
    protected $qrCode;
    protected $leaderboard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);

        $this->gameService = app(GameService::class);

        // Setup Game and QR Code
        $this->game = Game::factory()->create(['name' => 'Compete Game']);
        $this->qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'code' => 'COMPETE1',
        ]);

        // Link Game to QR Code
        // Note: Using 'skill' for win_mode as SQLite test DB has restricted enum values
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $this->qrCode->id,
            'game_id' => $this->game->id,
            'is_active' => true,
            'win_mode' => 'skill', 
        ]);

        // Create Leaderboard
        $this->leaderboard = Leaderboard::create([
            'business_id' => $this->business->id,
            'game_id' => $this->game->id,
            'name' => 'Weekly Challenge',
            'slug' => 'weekly-challenge',
            'type' => Leaderboard::TYPE_GAME_SPECIFIC,
            'score_type' => Leaderboard::SCORE_HIGHEST,
            'reset_frequency' => Leaderboard::RESET_WEEKLY,
            'is_active' => true,
        ]);
        
        // Initialize period
        $this->leaderboard->reset();
    }

    /** @test */
    public function rankings_update_correctly_when_users_compete()
    {
        $userA = User::factory()->create(['name' => 'Player A', 'role' => 'customer']);
        $userB = User::factory()->create(['name' => 'Player B', 'role' => 'customer']);

        // 1. User A plays and scores 100
        $this->playGame($userA, 100);

        // Verify Rank: A is #1
        $this->assertLeaderboardRank($userA, 1, 100);

        // 2. User B plays and scores 200
        $this->playGame($userB, 200);

        // Verify Rank: B is #1, A is #2
        $this->assertLeaderboardRank($userB, 1, 200);
        $this->assertLeaderboardRank($userA, 2, 100);

        // 3. User A plays again and scores 300 (improves)
        $this->playGame($userA, 300);

        // Verify Rank: A is #1, B is #2
        $this->assertLeaderboardRank($userA, 1, 300);
        $this->assertLeaderboardRank($userB, 2, 200);
    }

    /** @test */
    public function portal_api_returns_leaderboard_data()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        // User plays to get on the board
        $this->playGame($user, 500);

        // Fetch Portal Leaderboard Data
        $response = $this->get(route('portal.leaderboards.show', $this->leaderboard));

        $response->assertStatus(200);
        
        // Assert Inertia response using the callback
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Portal/LeaderboardDetail')
            ->where('leaderboard.id', $this->leaderboard->id)
            ->has('topEntries', 1)
            ->where('topEntries.0.user.id', $user->id)
            ->where('topEntries.0.rank', 1)
        );
    }

    /** @test */
    public function speed_challenge_records_time_correctly()
    {
        // Setup Speed Game (Win Mode: Time -> using 'skill' for SQLite compatibility)
        $speedGame = Game::factory()->create(['name' => 'Speed Run']);
        $speedQr = QRCode::factory()->create(['business_id' => $this->business->id]);
        
        QRCodeGame::create([
            'business_id' => $this->business->id,
            'qr_code_id' => $speedQr->id,
            'game_id' => $speedGame->id,
            'is_active' => true,
            'win_mode' => 'skill', 
        ]);

        $user = User::factory()->create();

        // Start Session
        $session = $this->gameService->startSession($speedQr, $speedGame, $user);
        
        // Start Play
        $play = $this->gameService->startPlaying($session);
        
        // Simulate 5 seconds passing
        $this->travel(5)->seconds();

        // Submit Score
        $this->gameService->submitScore($play, 5000);

        $play->refresh();
        
        // Duration should be approx 5 seconds
        // Allow variance of 1s
        $this->assertTrue($play->duration_seconds >= 5 && $play->duration_seconds <= 6);
    }

    // Helpers
    protected function playGame(User $user, int $score)
    {
        $session = $this->gameService->startSession($this->qrCode, $this->game, $user);
        $play = $this->gameService->startPlaying($session);
        $this->gameService->submitScore($play, $score);
    }

    protected function assertLeaderboardRank(User $user, int $rank, int $score)
    {
        $entry = $this->leaderboard->entries()
            ->where('user_id', $user->id)
            ->where('period_key', $this->leaderboard->getCurrentPeriodKey())
            ->first();

        $this->assertNotNull($entry, "User {$user->name} has no leaderboard entry.");
        $this->assertEquals($score, $entry->score, "User {$user->name} has incorrect score.");
        $this->assertEquals($rank, $entry->rank, "User {$user->name} has incorrect rank.");
    }
}
