<?php

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Services\PrizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrizeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PrizeService $prizeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prizeService = new PrizeService();
    }

    /** @test */
    public function it_creates_percentage_reward_correctly()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
            'user_id' => null,
        ]);

        $reward = $this->prizeService->createReward($gamePlay, $promotion->id, 'gold');

        $this->assertInstanceOf(GameReward::class, $reward);
        $this->assertEquals(GameReward::TYPE_PERCENTAGE, $reward->reward_type);
        $this->assertEquals(20.0, $reward->discount_value);
        $this->assertEquals($promotion->id, $reward->promotion_id);
        $this->assertNotNull($reward->reward_code);
        $this->assertEquals(GameReward::STATUS_AVAILABLE, $reward->status);
    }

    /** @test */
    public function it_applies_tier_multipliers_correctly()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
        ]);

        // Gold tier - 100% multiplier
        $goldReward = $this->prizeService->createReward($gamePlay, $promotion->id, 'gold');
        $this->assertEquals(20.0, $goldReward->discount_value);

        // Silver tier - 75% multiplier
        $silverReward = $this->prizeService->createReward(GamePlay::factory()->create(['business_id' => $business->id]), $promotion->id, 'silver');
        $this->assertEquals(15.0, $silverReward->discount_value);

        // Bronze tier - 50% multiplier
        $bronzeReward = $this->prizeService->createReward(GamePlay::factory()->create(['business_id' => $business->id]), $promotion->id, 'bronze');
        $this->assertEquals(10.0, $bronzeReward->discount_value);
    }

    /** @test */
    public function it_creates_fixed_amount_reward_correctly()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 10.00,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
        ]);

        $reward = $this->prizeService->createReward($gamePlay, $promotion->id, null);

        $this->assertEquals(GameReward::TYPE_FIXED, $reward->reward_type);
        $this->assertEquals(10.0, $reward->discount_value);
    }

    /** @test */
    public function it_creates_free_item_reward_correctly()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_BOGO,
            'name' => 'Free Coffee',
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
        ]);

        $reward = $this->prizeService->createReward($gamePlay, $promotion->id, null);

        $this->assertEquals(GameReward::TYPE_FREE_ITEM, $reward->reward_type);
        $this->assertEquals('Free Coffee', $reward->free_item);
    }

    /** @test */
    public function it_calculates_expiry_based_on_promotion_rules()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'rules' => ['reward_expires_days' => 7],
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
        ]);

        $reward = $this->prizeService->createReward($gamePlay, $promotion->id, null);

        $expectedExpiry = now()->addDays(7);
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $reward->expires_at->format('Y-m-d'));
    }

    /** @test */
    public function it_uses_default_expiry_when_no_rules_specified()
    {
        $business = Business::factory()->create();
        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'rules' => null,
        ]);
        $gamePlay = GamePlay::factory()->create([
            'business_id' => $business->id,
        ]);

        $reward = $this->prizeService->createReward($gamePlay, $promotion->id, null);

        $expectedExpiry = now()->addDays(30);
        $this->assertEquals($expectedExpiry->format('Y-m-d'), $reward->expires_at->format('Y-m-d'));
    }

    /** @test */
    public function it_returns_null_for_invalid_promotion()
    {
        $gamePlay = GamePlay::factory()->create();

        $reward = $this->prizeService->createReward($gamePlay, 99999, null);

        $this->assertNull($reward);
    }

    /** @test */
    public function it_determines_tier_based_on_score()
    {
        $scoreTiers = [
            'bronze' => 100,
            'silver' => 500,
            'gold' => 1000,
        ];

        $this->assertNull($this->prizeService->determineTier(50, $scoreTiers));
        $this->assertEquals('bronze', $this->prizeService->determineTier(150, $scoreTiers));
        $this->assertEquals('silver', $this->prizeService->determineTier(600, $scoreTiers));
        $this->assertEquals('gold', $this->prizeService->determineTier(1200, $scoreTiers));
    }

    /** @test */
    public function it_returns_reward_statistics()
    {
        $business = Business::factory()->create();
        
        GameReward::factory()->count(10)->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'discount_value' => 10.00,
            'tier' => 'gold',
            'reward_type' => GameReward::TYPE_PERCENTAGE,
            'created_at' => now()->subDays(15),
        ]);

        GameReward::factory()->count(5)->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_REDEEMED,
            'discount_value' => 10.00,
            'created_at' => now()->subDays(15),
        ]);

        $stats = $this->prizeService->getRewardStats($business->id, 30);

        $this->assertEquals(15, $stats['total_given']);
        $this->assertEquals(5, $stats['total_redeemed']);
        $this->assertEquals(150.0, $stats['total_value_given']);
        $this->assertEquals(50.0, $stats['total_value_redeemed']);
        $this->assertEquals(10, $stats['by_tier']['gold']);
    }
}

