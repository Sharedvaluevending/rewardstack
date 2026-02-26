<?php

namespace Tests\Unit\Notifications;

use App\Models\GameReward;
use App\Notifications\PortalRewardWon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRewardWonTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $reward = GameReward::factory()->create(['description' => '10% off']);
        $notification = new PortalRewardWon($reward);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $reward = GameReward::factory()->create(['description' => 'Free coffee']);
        $notification = new PortalRewardWon($reward);
        $data = $notification->toArray($reward->user);

        $this->assertSame('You won a reward: Free coffee', $data['message']);
        $this->assertSame('/portal/rewards/' . $reward->id, $data['action_url']);
        $this->assertSame('reward_won', $data['type']);
        $this->assertSame('🎁', $data['icon']);
        $this->assertSame($reward->id, $data['reward_id']);
    }

    public function test_to_array_uses_reward_fallback_when_description_null(): void
    {
        $reward = GameReward::factory()->create(['description' => null]);
        $notification = new PortalRewardWon($reward);
        $data = $notification->toArray($reward->user);
        $this->assertSame('You won a reward: Reward', $data['message']);
    }
}
