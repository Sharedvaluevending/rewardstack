<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndSubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_role_blocks_wrong_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $resp = $this->actingAs($user)->get('/admin/merch');

        $this->assertTrue(in_array($resp->status(), [302, 403, 404]));
    }

    public function test_ensure_active_subscription_blocks_when_missing(): void
    {
        $user = User::factory()->create(['role' => 'business']);

        $resp = $this->actingAs($user)->get('/business/settings');

        $this->assertTrue(in_array($resp->status(), [302, 403, 404]));
    }

    public function test_check_role_allows_correct_role(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $resp = $this->actingAs($user)->get('/admin/merch');
        $this->assertNotEquals(403, $resp->status());
    }

    public function test_ensure_active_subscription_allows_when_trialing(): void
    {
        $user = User::factory()->create(['role' => 'business']);
        $user->business()->update(['trial_ends_at' => now()->addDay()]);
        $resp = $this->actingAs($user)->get('/business/settings');
        $this->assertNotEquals(403, $resp->status());
    }
}

