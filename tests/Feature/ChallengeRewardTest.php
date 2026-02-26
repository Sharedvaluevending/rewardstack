<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Game;
use App\Models\GameReward;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ChallengeRewardTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $game;
    protected $promotion;
    protected $leaderboard;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup common entities
        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);
        
        $this->game = Game::factory()->create([
            'is_active' => true,
            'slug' => 'test-game',
        ]);
        
        $this->promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'name' => 'Winner Prize',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 10.00,
        ]);
    }

    /** @test */
    public function prizes_are_awarded_to_top_players_when_period_ends()
    {
        // 1. Setup Leaderboard that just ended
        $now = now();
        $endOfYesterday = $now->copy()->subDay()->endOfDay(); // Correct: Yesterday 23:59:59
        $periodKey = $endOfYesterday->format('Y-m-d');

        $leaderboard = Leaderboard::create([
            'business_id' => $this->business->id,
            'game_id' => $this->game->id,
            'name' => 'Daily Challenge',
            'slug' => 'daily-challenge',
            'type' => Leaderboard::TYPE_GAME_SPECIFIC,
            'reset_frequency' => Leaderboard::RESET_DAILY,
            'current_period_start' => $endOfYesterday->copy()->startOfDay(),
            'current_period_end' => $endOfYesterday, // Ended in the past
            'promotion_id' => $this->promotion->id,
            'score_type' => Leaderboard::SCORE_HIGHEST,
            'is_active' => true,
            'prize_config' => ['top_players' => 3],
        ]);

        // 2. Create Users and Entries (manually creating entries to simulate past period)
        $users = User::factory()->count(4)->create(['role' => 'customer']);
        
        // Rank 1
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[0]->id,
            'business_id' => $this->business->id,
            'score' => 1000,
            'rank' => 1,
            'period_key' => $periodKey,
        ]);

        // Rank 2
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[1]->id,
            'business_id' => $this->business->id,
            'score' => 900,
            'rank' => 2,
            'period_key' => $periodKey,
        ]);

        // Rank 3
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[2]->id,
            'business_id' => $this->business->id,
            'score' => 800,
            'rank' => 3,
            'period_key' => $periodKey,
        ]);

        // Rank 4 (Should NOT win)
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[3]->id,
            'business_id' => $this->business->id,
            'score' => 700,
            'rank' => 4,
            'period_key' => $periodKey,
        ]);

        // 3. Run the command
        Artisan::call('leaderboards:award-prizes');

        // 4. Assertions
        
        // User 1 (Rank 1) should have a reward
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $users[0]->id,
            'promotion_id' => $this->promotion->id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        // User 2 (Rank 2) should have a reward
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $users[1]->id,
            'promotion_id' => $this->promotion->id,
        ]);

        // User 3 (Rank 3) should have a reward
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $users[2]->id,
            'promotion_id' => $this->promotion->id,
        ]);

        // User 4 (Rank 4) should NOT have a reward
        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $users[3]->id,
            'promotion_id' => $this->promotion->id,
        ]);

        // Leaderboard should have reset (end date pushed to future)
        $leaderboard->refresh();
        $this->assertTrue($leaderboard->current_period_end->isFuture());
    }

    /** @test */
    public function leaderboard_prizes_respect_configured_winner_count()
    {
        // 1. Setup Leaderboard (Top 1 only)
        $now = now();
        $endOfYesterday = $now->copy()->subDay()->endOfDay(); // Correct: Yesterday 23:59:59
        $periodKey = $endOfYesterday->format('Y-m-d');

        $leaderboard = Leaderboard::create([
            'business_id' => $this->business->id,
            'game_id' => $this->game->id,
            'name' => 'Elite Challenge',
            'slug' => 'elite-challenge',
            'type' => Leaderboard::TYPE_GAME_SPECIFIC,
            'reset_frequency' => Leaderboard::RESET_DAILY,
            'current_period_start' => $endOfYesterday->copy()->startOfDay(),
            'current_period_end' => $endOfYesterday,
            'promotion_id' => $this->promotion->id,
            'score_type' => Leaderboard::SCORE_HIGHEST,
            'is_active' => true,
            'prize_config' => ['top_players' => 1], // Only 1 winner
        ]);

        $users = User::factory()->count(2)->create(['role' => 'customer']);
        
        // Rank 1
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[0]->id,
            'business_id' => $this->business->id,
            'score' => 1000,
            'rank' => 1,
            'period_key' => $periodKey,
        ]);

        // Rank 2
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $users[1]->id,
            'business_id' => $this->business->id,
            'score' => 900,
            'rank' => 2,
            'period_key' => $periodKey,
        ]);

        Artisan::call('leaderboards:award-prizes');

        // User 1 wins
        $this->assertDatabaseHas('game_rewards', [
            'user_id' => $users[0]->id,
            'promotion_id' => $this->promotion->id,
        ]);

        // User 2 loses
        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $users[1]->id,
            'promotion_id' => $this->promotion->id,
        ]);
    }

    /** @test */
    public function prizes_are_not_awarded_if_period_has_not_ended()
    {
        // Future end date
        $leaderboard = Leaderboard::create([
            'business_id' => $this->business->id,
            'game_id' => $this->game->id,
            'name' => 'Ongoing Challenge',
            'slug' => 'ongoing-challenge',
            'type' => Leaderboard::TYPE_GAME_SPECIFIC,
            'reset_frequency' => Leaderboard::RESET_DAILY,
            'current_period_start' => now()->startOfDay(),
            'current_period_end' => now()->addHour(), // Future
            'promotion_id' => $this->promotion->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['role' => 'customer']);
        LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $user->id,
            'business_id' => $this->business->id,
            'score' => 1000,
            'rank' => 1,
            'period_key' => now()->format('Y-m-d'),
        ]);

        Artisan::call('leaderboards:award-prizes');

        $this->assertDatabaseMissing('game_rewards', [
            'user_id' => $user->id,
            'promotion_id' => $this->promotion->id,
        ]);
    }
}
