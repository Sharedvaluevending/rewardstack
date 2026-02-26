<?php

namespace Tests\Unit\Policies;

use App\Models\Business;
use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allows_when_user_owns_business(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['business_id' => $business->id]);

        $policy = new OrderPolicy();
        $this->assertTrue($policy->view($user, $order));
    }

    public function test_view_denies_when_user_does_not_own_business(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $otherBusiness = Business::factory()->create();
        $order = Order::factory()->create(['business_id' => $otherBusiness->id]);

        $policy = new OrderPolicy();
        $this->assertFalse($policy->view($user, $order));
    }

    public function test_update_allows_when_own_business_and_order_pending(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['business_id' => $business->id, 'status' => 'pending']);

        $policy = new OrderPolicy();
        $this->assertTrue($policy->update($user, $order));
    }

    public function test_update_denies_when_order_not_pending(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['business_id' => $business->id, 'status' => 'shipped']);

        $policy = new OrderPolicy();
        $this->assertFalse($policy->update($user, $order));
    }

    public function test_delete_allows_when_own_business_and_order_pending(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['business_id' => $business->id, 'status' => 'pending']);

        $policy = new OrderPolicy();
        $this->assertTrue($policy->delete($user, $order));
    }

    public function test_delete_denies_when_order_not_pending(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $business = Business::factory()->create(['user_id' => $user->id]);
        $order = Order::factory()->create(['business_id' => $business->id, 'status' => 'processing']);

        $policy = new OrderPolicy();
        $this->assertFalse($policy->delete($user, $order));
    }
}
