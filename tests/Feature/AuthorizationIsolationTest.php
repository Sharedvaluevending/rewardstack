<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_cannot_view_other_business_order(): void
    {
        $bizA = Business::factory()->create();
        $bizB = Business::factory()->create();

        $userA = User::factory()->create(['role' => 'business']);
        $bizA->update(['user_id' => $userA->id]);

        $orderB = Order::factory()->create([
            'business_id' => $bizB->id,
        ]);

        $resp = $this->actingAs($userA)->get("/portal/orders/{$orderB->id}");

        $this->assertTrue(in_array($resp->status(), [403, 404]));
    }

    public function test_admin_only_route_blocked_for_business_user(): void
    {
        $user = User::factory()->create(['role' => 'business']);

        $resp = $this->actingAs($user)->post('/admin/merch/sync', []);

        $this->assertTrue(in_array($resp->status(), [403, 404, 302]));
    }
}

