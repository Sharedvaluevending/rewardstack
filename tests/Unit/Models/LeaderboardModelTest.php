<?php

namespace Tests\Unit\Models;

use App\Models\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_and_reset_constants_are_defined(): void
    {
        $this->assertSame('location', Leaderboard::TYPE_LOCATION);
        $this->assertSame('multi_location', Leaderboard::TYPE_MULTI_LOCATION);
        $this->assertSame('never', Leaderboard::RESET_NEVER);
        $this->assertSame('daily', Leaderboard::RESET_DAILY);
        $this->assertSame('weekly', Leaderboard::RESET_WEEKLY);
        $this->assertSame('highest', Leaderboard::SCORE_HIGHEST);
    }

    public function test_type_and_score_constants_complete(): void
    {
        $this->assertSame('game_specific', Leaderboard::TYPE_GAME_SPECIFIC);
        $this->assertSame('global', Leaderboard::TYPE_GLOBAL);
        $this->assertSame('monthly', Leaderboard::RESET_MONTHLY);
        $this->assertSame('seasonal', Leaderboard::RESET_SEASONAL);
        $this->assertSame('cumulative', Leaderboard::SCORE_CUMULATIVE);
        $this->assertSame('average', Leaderboard::SCORE_AVERAGE);
    }

    public function test_get_current_period_key_daily_returns_date_format(): void
    {
        $leaderboard = Leaderboard::factory()->create(['reset_frequency' => Leaderboard::RESET_DAILY]);
        $key = $leaderboard->getCurrentPeriodKey();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $key);
    }

    public function test_get_current_period_key_weekly_returns_week_format(): void
    {
        $leaderboard = Leaderboard::factory()->create(['reset_frequency' => Leaderboard::RESET_WEEKLY]);
        $key = $leaderboard->getCurrentPeriodKey();
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{2}$/', $key);
    }

    public function test_get_current_period_key_monthly_returns_month_format(): void
    {
        $leaderboard = Leaderboard::factory()->create(['reset_frequency' => Leaderboard::RESET_MONTHLY]);
        $key = $leaderboard->getCurrentPeriodKey();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $key);
    }

    public function test_get_current_period_key_never_returns_all_time(): void
    {
        $leaderboard = Leaderboard::factory()->create(['reset_frequency' => Leaderboard::RESET_NEVER]);
        $this->assertSame('all-time', $leaderboard->getCurrentPeriodKey());
    }

    public function test_scope_active_filters_is_active(): void
    {
        $active = Leaderboard::factory()->create(['is_active' => true]);
        $inactive = Leaderboard::factory()->create(['is_active' => false]);
        $ids = Leaderboard::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_scope_for_business_includes_business_id_and_json(): void
    {
        $b1 = \App\Models\Business::factory()->create();
        $b2 = \App\Models\Business::factory()->create();
        $b3 = \App\Models\Business::factory()->create();
        $lb1 = Leaderboard::factory()->create(['business_id' => $b1->id]);
        $lb2 = Leaderboard::factory()->create(['business_id' => $b2->id, 'business_ids' => [$b1->id, $b3->id]]);
        $lb3 = Leaderboard::factory()->create(['business_id' => $b3->id]);
        $ids = Leaderboard::forBusiness($b1->id)->pluck('id')->all();
        $this->assertContains($lb1->id, $ids);
        $this->assertContains($lb2->id, $ids);
        $this->assertNotContains($lb3->id, $ids);
    }

    public function test_get_top_entries_returns_entries_ordered_by_score(): void
    {
        $leaderboard = Leaderboard::factory()->create([
            'reset_frequency' => Leaderboard::RESET_WEEKLY,
        ]);
        $periodKey = $leaderboard->getCurrentPeriodKey();
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        $e1 = \App\Models\LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $user1->id,
            'business_id' => $leaderboard->business_id,
            'period_key' => $periodKey,
            'score' => 100,
            'rank' => 2,
        ]);
        $e2 = \App\Models\LeaderboardEntry::create([
            'leaderboard_id' => $leaderboard->id,
            'user_id' => $user2->id,
            'business_id' => $leaderboard->business_id,
            'period_key' => $periodKey,
            'score' => 200,
            'rank' => 1,
        ]);

        $top = $leaderboard->getTopEntries(5);
        $this->assertCount(2, $top);
        $this->assertSame($e2->id, $top->first()->id);
        $this->assertSame(200, $top->first()->score);
    }

    public function test_get_top_entries_respects_limit(): void
    {
        $leaderboard = Leaderboard::factory()->create([
            'reset_frequency' => Leaderboard::RESET_DAILY,
        ]);
        $periodKey = $leaderboard->getCurrentPeriodKey();
        for ($i = 0; $i < 5; $i++) {
            \App\Models\LeaderboardEntry::create([
                'leaderboard_id' => $leaderboard->id,
                'user_id' => \App\Models\User::factory()->create()->id,
                'business_id' => $leaderboard->business_id,
                'period_key' => $periodKey,
                'score' => 100 - $i,
                'rank' => $i + 1,
            ]);
        }
        $top = $leaderboard->getTopEntries(3);
        $this->assertCount(3, $top);
    }
}
