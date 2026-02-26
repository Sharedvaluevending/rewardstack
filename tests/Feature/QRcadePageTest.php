<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class QRcadePageTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);
        
        $this->owner = $this->business->owner;
        $this->owner->update(['role' => 'business']);
    }

    /** @test */
    public function qrcade_dashboard_loads_without_error()
    {
        // Create some sample data to populate stats
        GamePlay::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('business.qrcade'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Business/QRcade/Index')
            ->has('stats')
            ->has('recentPlays')
            ->has('topPlayers')
            ->has('enabledGames')
        );
    }

    /** @test */
    public function qrcade_analytics_loads_without_error()
    {
        // Create sample data
        GamePlay::factory()->count(10)->create([
            'business_id' => $this->business->id,
            'created_at' => now()->subDay(),
            'score' => 100,
        ]);

        // Create a reward to populate reward stats
        $play = GamePlay::factory()->create(['business_id' => $this->business->id]);
        GameReward::create([
            'reward_code' => 'TEST1234',
            'game_play_id' => $play->id,
            'user_id' => User::factory()->create()->id,
            'business_id' => $this->business->id,
            'reward_type' => 'percentage', // Add required type
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('business.qrcade.analytics'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Business/QRcade/Analytics')
            ->has('summary') // Uses getBusinessGameStats
            ->has('dailyStats')
            ->has('gameStats')
            ->has('peakHours')
            ->has('rewardStats')
        );
    }
}
