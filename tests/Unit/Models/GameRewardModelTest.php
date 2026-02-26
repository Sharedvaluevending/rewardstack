<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GameRewardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_and_type_constants_are_defined(): void
    {
        $this->assertSame('available', GameReward::STATUS_AVAILABLE);
        $this->assertSame('claimed', GameReward::STATUS_CLAIMED);
        $this->assertSame('redeemed', GameReward::STATUS_REDEEMED);
        $this->assertSame('expired', GameReward::STATUS_EXPIRED);
        $this->assertSame('percentage', GameReward::TYPE_PERCENTAGE);
        $this->assertSame('fixed', GameReward::TYPE_FIXED);
        $this->assertSame('free_item', GameReward::TYPE_FREE_ITEM);
    }

    public function test_scope_available_filters_by_status_and_expiry(): void
    {
        Notification::fake();
        $play = GamePlay::factory()->create();
        $available = GameReward::factory()->create([
            'game_play_id' => $play->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'promotion_id' => Promotion::factory()->create()->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(7),
        ]);
        $redeemed = GameReward::factory()->redeemed()->create([
            'game_play_id' => $play->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'promotion_id' => Promotion::factory()->create()->id,
        ]);
        $expired = GameReward::factory()->expired()->create([
            'game_play_id' => $play->id,
            'user_id' => $play->user_id,
            'business_id' => $play->business_id,
            'promotion_id' => Promotion::factory()->create()->id,
        ]);

        $ids = GameReward::available()->pluck('id')->all();
        $this->assertContains($available->id, $ids);
        $this->assertNotContains($redeemed->id, $ids);
        $this->assertNotContains($expired->id, $ids);
    }

    public function test_is_available_returns_false_when_not_available_status(): void
    {
        Notification::fake();
        $reward = GameReward::factory()->redeemed()->create();
        $this->assertFalse($reward->isAvailable());
    }

    public function test_is_available_returns_false_when_expired(): void
    {
        Notification::fake();
        $reward = GameReward::factory()->expired()->create();
        $this->assertFalse($reward->isAvailable());
    }

    public function test_is_available_returns_true_when_available_and_not_expired(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-02-22 12:00:00'));
        $reward = GameReward::factory()->create([
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(7),
            'valid_from' => now()->subDay(),
        ]);
        $this->assertTrue($reward->isAvailable());
    }

    public function test_claim_updates_status_to_claimed(): void
    {
        Notification::fake();
        $reward = GameReward::factory()->create([
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(7),
        ]);
        $result = $reward->claim();
        $this->assertTrue($result);
        $reward->refresh();
        $this->assertSame(GameReward::STATUS_CLAIMED, $reward->status);
        $this->assertNotNull($reward->claimed_at);
    }

    public function test_claim_returns_false_when_not_available(): void
    {
        Notification::fake();
        $reward = GameReward::factory()->redeemed()->create();
        $this->assertFalse($reward->claim());
    }

    public function test_scope_for_user_filters_by_user_id(): void
    {
        Notification::fake();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $play1 = GamePlay::factory()->create(['user_id' => $user1->id]);
        $play2 = GamePlay::factory()->create(['user_id' => $user2->id]);
        $r1 = GameReward::factory()->create(['game_play_id' => $play1->id, 'user_id' => $user1->id]);
        $r2 = GameReward::factory()->create(['game_play_id' => $play2->id, 'user_id' => $user2->id]);

        $ids = GameReward::forUser($user1->id)->pluck('id')->all();
        $this->assertContains($r1->id, $ids);
        $this->assertNotContains($r2->id, $ids);
    }
}
