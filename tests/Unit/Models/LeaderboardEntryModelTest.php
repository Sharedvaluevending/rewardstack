<?php

namespace Tests\Unit\Models;

use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardEntryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_rank_change_icon_returns_up_when_positive(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => 2]);
        $this->assertSame('↑', $entry->getRankChangeIcon());
    }

    public function test_get_rank_change_icon_returns_down_when_negative(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => -1]);
        $this->assertSame('↓', $entry->getRankChangeIcon());
    }

    public function test_get_rank_change_icon_returns_minus_when_zero(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => 0]);
        $this->assertSame('−', $entry->getRankChangeIcon());
    }

    public function test_get_rank_change_class_returns_green_when_positive(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => 1]);
        $this->assertSame('text-green-500', $entry->getRankChangeClass());
    }

    public function test_get_rank_change_class_returns_red_when_negative(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => -1]);
        $this->assertSame('text-red-500', $entry->getRankChangeClass());
    }

    public function test_get_rank_change_class_returns_gray_when_zero(): void
    {
        $entry = new LeaderboardEntry(['rank_change' => 0]);
        $this->assertSame('text-gray-400', $entry->getRankChangeClass());
    }

    public function test_is_top_three_returns_true_for_ranks_one_two_three(): void
    {
        $this->assertTrue((new LeaderboardEntry(['rank' => 1]))->isTopThree());
        $this->assertTrue((new LeaderboardEntry(['rank' => 2]))->isTopThree());
        $this->assertTrue((new LeaderboardEntry(['rank' => 3]))->isTopThree());
    }

    public function test_is_top_three_returns_false_for_rank_four(): void
    {
        $this->assertFalse((new LeaderboardEntry(['rank' => 4]))->isTopThree());
    }

    public function test_get_medal_emoji_returns_gold_silver_bronze_for_top_three(): void
    {
        $this->assertSame('🥇', (new LeaderboardEntry(['rank' => 1]))->getMedalEmoji());
        $this->assertSame('🥈', (new LeaderboardEntry(['rank' => 2]))->getMedalEmoji());
        $this->assertSame('🥉', (new LeaderboardEntry(['rank' => 3]))->getMedalEmoji());
    }

    public function test_get_medal_emoji_returns_null_for_rank_four(): void
    {
        $this->assertNull((new LeaderboardEntry(['rank' => 4]))->getMedalEmoji());
    }
}
