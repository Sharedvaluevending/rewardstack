<?php

namespace Tests\Feature\Console;

use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\PortalRewardExpiringSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyExpiringRewardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_notifies_zero_when_no_expiring_rewards(): void
    {
        $this->artisan('rewards:notify-expiring')
            ->expectsOutputToContain('Notified 0 expiring rewards.')
            ->assertExitCode(0);
    }

    public function test_command_sends_notification_for_expiring_reward(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => 'user']);
        $promotion = Promotion::factory()->create();
        $reward = GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promotion->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(2),
        ]);

        $this->artisan('rewards:notify-expiring', ['--days' => 3])
            ->expectsOutputToContain('Notified 1 expiring rewards.')
            ->assertExitCode(0);

        Notification::assertSentTo($user, PortalRewardExpiringSoon::class);
    }

    public function test_command_skips_non_portal_users(): void
    {
        Notification::fake();

        $businessUser = User::factory()->create(['role' => 'business']);
        $promotion = Promotion::factory()->create();
        GameReward::factory()->create([
            'user_id' => $businessUser->id,
            'promotion_id' => $promotion->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(2),
        ]);

        $this->artisan('rewards:notify-expiring', ['--days' => 3])
            ->expectsOutputToContain('Notified 0 expiring rewards.')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
