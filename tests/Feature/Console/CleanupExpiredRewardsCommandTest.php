<?php

namespace Tests\Feature\Console;

use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupExpiredRewardsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_when_no_expired_rewards(): void
    {
        $this->artisan('rewards:cleanup-expired')
            ->expectsOutputToContain('No expired rewards found that need cleanup.')
            ->assertExitCode(0);
    }

    public function test_command_dry_run_shows_rewards_without_deleting(): void
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create();
        GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promotion->id,
            'status' => GameReward::STATUS_EXPIRED,
            'reward_code' => null,
            'qr_image_path' => null,
        ]);

        $this->artisan('rewards:cleanup-expired', ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 expired reward(s)')
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertDatabaseCount('game_rewards', 1);
    }

    public function test_command_deletes_expired_rewards_when_confirmed(): void
    {
        $user = User::factory()->create();
        $promotion = Promotion::factory()->create();
        GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promotion->id,
            'status' => GameReward::STATUS_EXPIRED,
            'reward_code' => null,
            'qr_image_path' => null,
        ]);

        $this->artisan('rewards:cleanup-expired')
            ->expectsQuestion('Do you want to delete these expired rewards?', 'yes')
            ->expectsOutputToContain('Successfully deleted')
            ->assertExitCode(0);

        $this->assertDatabaseCount('game_rewards', 0);
    }
}
