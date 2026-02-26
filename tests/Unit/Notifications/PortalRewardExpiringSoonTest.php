<?php

namespace Tests\Unit\Notifications;

use App\Models\GameReward;
use App\Notifications\PortalRewardExpiringSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRewardExpiringSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $reward = GameReward::factory()->create();
        $notification = new PortalRewardExpiringSoon($reward, 2);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $reward = GameReward::factory()->create([
            'description' => '20% off',
            'expires_at' => now()->addDays(2),
        ]);
        $notification = new PortalRewardExpiringSoon($reward, 2);
        $data = $notification->toArray($reward->user);

        $this->assertStringContainsString('Reward expiring in 2 days: 20% off', $data['message']);
        $this->assertSame('/portal/rewards/' . $reward->id, $data['action_url']);
        $this->assertSame('reward_expiring', $data['type']);
        $this->assertSame('⏰', $data['icon']);
        $this->assertSame($reward->id, $data['reward_id']);
        $this->assertSame($reward->expires_at->toDateString(), $data['expires_at']);
    }

    public function test_to_array_uses_one_day_text_when_days_left_is_one(): void
    {
        $reward = GameReward::factory()->create(['description' => 'Reward', 'expires_at' => now()->addDay()]);
        $notification = new PortalRewardExpiringSoon($reward, 1);
        $data = $notification->toArray($reward->user);
        $this->assertStringContainsString('1 day', $data['message']);
    }
}
