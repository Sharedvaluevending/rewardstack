<?php

namespace Tests\Unit\Notifications;

use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\User;
use App\Notifications\PortalLeaderboardPrize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLeaderboardPrizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $leaderboard = Leaderboard::factory()->create(['name' => 'Weekly']);
        $play = GamePlay::factory()->create();
        $reward = GameReward::factory()->create(['user_id' => $play->user_id, 'leaderboard_entry_id' => null]);
        $entry = LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'score' => 100,
            'rank' => 1,
        ]);
        $notification = new PortalLeaderboardPrize($leaderboard, $reward, $entry);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $leaderboard = Leaderboard::factory()->create(['name' => 'Weekly Challenge']);
        $play = GamePlay::factory()->create();
        $reward = GameReward::factory()->create(['user_id' => $play->user_id]);
        $entry = LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'score' => 500,
            'rank' => 2,
        ]);
        $notification = new PortalLeaderboardPrize($leaderboard, $reward, $entry);
        $data = $notification->toArray($play->user);

        $this->assertStringContainsString('Leaderboard prize awarded (#2)', $data['message']);
        $this->assertStringContainsString('Weekly Challenge', $data['message']);
        $this->assertSame('/portal/rewards/' . $reward->id, $data['action_url']);
        $this->assertSame('leaderboard_prize', $data['type']);
        $this->assertSame('🏆', $data['icon']);
        $this->assertSame($leaderboard->id, $data['leaderboard_id']);
        $this->assertSame($reward->id, $data['reward_id']);
        $this->assertSame(2, $data['rank']);
    }

    public function test_to_array_uses_top_spot_when_rank_null(): void
    {
        $leaderboard = Leaderboard::factory()->create(['name' => 'LB']);
        $play = GamePlay::factory()->create();
        $reward = GameReward::factory()->create(['user_id' => $play->user_id]);
        $entry = LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'score' => 100,
            'rank' => null,
        ]);
        $notification = new PortalLeaderboardPrize($leaderboard, $reward, $entry);
        $data = $notification->toArray($play->user);
        $this->assertStringContainsString('a top spot', $data['message']);
    }
}
