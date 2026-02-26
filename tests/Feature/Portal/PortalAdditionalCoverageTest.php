<?php

namespace Tests\Feature\Portal;

use App\Models\Business;
use App\Models\Game;
use App\Models\GameReward;
use App\Models\Leaderboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalAdditionalCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['role' => 'customer']);
    }

    public function test_rewards_show_returns_200_for_own_reward(): void
    {
        $business = Business::factory()->create();
        $reward = GameReward::factory()->create([
            'user_id' => $this->customer->id,
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $response = $this->actingAs($this->customer)
            ->get(route('portal.rewards.show', $reward));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/RewardDetail')
                ->has('reward')
            );
    }

    public function test_leaderboards_show_returns_200(): void
    {
        $game = Game::factory()->create(['is_active' => true]);
        $leaderboard = Leaderboard::factory()->create([
            'game_id' => $game->id,
            'type' => 'global',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->get(route('portal.leaderboards.show', $leaderboard));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/LeaderboardDetail')
                ->has('leaderboard')
                ->has('topEntries')
            );
    }

    public function test_subscriptions_index_returns_200(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('portal.subscriptions'));

        $response->assertStatus(200);
    }

    public function test_stripe_connect_redirects_or_returns_200(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('portal.stripe.connect'));

        $this->assertContains($response->status(), [200, 302]);
    }
}
