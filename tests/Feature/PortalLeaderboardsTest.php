<?php

namespace Tests\Feature;

use App\Models\Leaderboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalLeaderboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_leaderboards_page_loads_for_customer(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Leaderboard::factory()->create(['type' => 'global', 'is_active' => true]);

        $this->actingAs($user)
            ->get('/portal/leaderboards')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Leaderboards'));
    }
}
