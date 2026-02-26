<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MerchUnlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchUnlockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_reward_multiplier_returns_one_when_user_has_no_unlocks(): void
    {
        $user = User::factory()->create();
        $service = new MerchUnlockService();
        $this->assertSame(1.0, $service->getRewardMultiplier($user));
    }

    public function test_get_extra_plays_per_day_returns_zero_when_user_has_no_unlocks(): void
    {
        $user = User::factory()->create();
        $service = new MerchUnlockService();
        $this->assertSame(0, $service->getExtraPlaysPerDay($user));
    }

    public function test_expire_old_unlocks_returns_zero_when_none_to_expire(): void
    {
        $service = new MerchUnlockService();
        $count = $service->expireOldUnlocks();
        $this->assertSame(0, $count);
    }

    public function test_process_order_returns_empty_when_order_has_no_user(): void
    {
        $order = \App\Models\Order::factory()->create();
        $service = new MerchUnlockService();
        $unlocks = $service->processOrder($order);
        $this->assertSame([], $unlocks);
    }

    public function test_has_game_access_returns_false_when_user_has_no_unlocks(): void
    {
        $user = User::factory()->create();
        $service = new MerchUnlockService();
        $this->assertFalse($service->hasGameAccess($user, 1));
    }

    public function test_get_active_unlocks_returns_empty_collection_when_none(): void
    {
        $user = User::factory()->create();
        $service = new MerchUnlockService();
        $unlocks = $service->getActiveUnlocks($user);
        $this->assertCount(0, $unlocks);
    }

    public function test_handle_delivery_does_nothing_when_no_pending_unlocks(): void
    {
        $service = new MerchUnlockService();
        $service->handleDelivery('non-existent-printful-id');
        $this->assertTrue(true);
    }

    public function test_get_merch_with_unlocks_returns_collection(): void
    {
        $service = new MerchUnlockService();
        $merch = $service->getMerchWithUnlocks();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $merch);
    }
}
