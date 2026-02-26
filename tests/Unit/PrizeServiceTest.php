<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\GameSession;
use App\Services\PrizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrizeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGameplay(User $user, Business $business, Game $game, QRCode $qr): GamePlay
    {
        $session = GameSession::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'status' => GameSession::STATUS_ACTIVE,
        ]);

        return GamePlay::create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'qr_code_id' => $qr->id,
            'score' => 100,
            'duration_seconds' => 30,
            'result' => GamePlay::RESULT_WIN,
            'reward_tier' => GamePlay::TIER_SILVER,
            'started_at' => now(),
        ]);
    }

    public function test_create_reward_returns_null_if_promotion_missing(): void
    {
        $service = app(PrizeService::class);
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => null]);
        $play = $this->makeGameplay($user, $business, $game, $qr);

        $reward = $service->createReward($play, 9999);

        $this->assertNull($reward);
        $this->assertEquals(0, GameReward::count());
    }

    public function test_create_reward_blocks_duplicate_for_same_gameplay(): void
    {
        $service = app(PrizeService::class);
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 10,
            'rules' => [],
        ]);
        $play = $this->makeGameplay($user, $business, $game, $qr);

        $first = $service->createReward($play, $promo->id);
        $second = $service->createReward($play, $promo->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertEquals(1, GameReward::count());
    }

    public function test_create_reward_respects_per_user_limit(): void
    {
        $service = app(PrizeService::class);
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'rules' => ['max_redemptions_per_user' => 1],
        ]);
        $play = $this->makeGameplay($user, $business, $game, $qr);

        GameReward::create([
            'reward_code' => 'EXISTING',
            'game_play_id' => $play->id,
            'user_id' => $user->id,
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'reward_type' => GameReward::TYPE_FIXED,
            'discount_value' => 5,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $new = $service->createReward($play, $promo->id);

        $this->assertNotNull($new);
        $this->assertEquals('EXISTING', $new->reward_code);
        $this->assertEquals(1, GameReward::where('promotion_id', $promo->id)->count());
    }

    public function test_create_reward_applies_tier_and_sets_fields(): void
    {
        $service = app(PrizeService::class);
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 20,
            'rules' => ['reward_expires_days' => 10, 'max_redemptions_per_user' => 5],
            'ends_at' => now()->addDays(30),
            'description' => 'Save big',
        ]);
        $play = $this->makeGameplay($user, $business, $game, $qr);

        $reward = $service->createReward($play, $promo->id, GamePlay::TIER_SILVER);

        $this->assertNotNull($reward);
        $this->assertEquals(GameReward::TYPE_FIXED, $reward->reward_type);
        $this->assertEquals(15.0, $reward->discount_value); // 20 * 0.75 tier multiplier
        $this->assertEquals(GameReward::STATUS_AVAILABLE, $reward->status);
        $this->assertNotEmpty($reward->reward_code);
        $this->assertEquals($promo->id, $reward->promotion_id);
        $this->assertEquals($business->id, $reward->business_id);
        $this->assertEquals($user->id, $reward->user_id);
        $this->assertEquals($play->id, $reward->game_play_id);
        $this->assertNotNull($reward->expires_at);
        $this->assertTrue($reward->expires_at->greaterThan(now()->addDays(5)));
        $this->assertNotNull($reward->valid_until);
        $this->assertEquals($promo->ends_at->toDateString(), $reward->valid_until->toDateString());
    }

    public function test_get_mystery_prize_returns_null_when_no_promotions(): void
    {
        $business = Business::factory()->create();
        $service = app(PrizeService::class);
        $result = $service->getMysteryPrize($business->id);
        $this->assertNull($result);
    }

    public function test_get_mystery_prize_returns_promotion_when_available(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'ends_at' => now()->addDay(),
        ]);
        $service = app(PrizeService::class);
        $result = $service->getMysteryPrize($business->id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('promotion_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertEquals($promo->id, $result['promotion_id']);
    }

    public function test_calculate_reward_delegates_to_qr_code_game(): void
    {
        $business = Business::factory()->create();
        $game = Game::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $qrCodeGame = \App\Models\QRCodeGame::factory()->scoreBased(100)->create([
            'qr_code_id' => $qrCode->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'score_tiers' => ['gold' => 500, 'silver' => 200, 'bronze' => 100],
            'tier_rewards' => [],
        ]);
        $service = app(PrizeService::class);
        $result = $service->calculateReward($qrCodeGame, 150);
        $this->assertIsArray($result);
    }

    public function test_create_badge_reward_creates_reward_with_badge_type(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);
        $session = GameSession::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'game_id' => Game::factory()->create()->id,
            'business_id' => $business->id,
            'location_status' => GameSession::LOCATION_VERIFIED,
            'status' => GameSession::STATUS_ACTIVE,
        ]);
        $play = GamePlay::create([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
            'game_id' => $session->game_id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
            'score' => 100,
            'duration_seconds' => 30,
            'result' => GamePlay::RESULT_WIN,
            'started_at' => now(),
        ]);
        $service = app(PrizeService::class);
        $reward = $service->createBadgeReward($play, 'Test Badge');
        $this->assertInstanceOf(GameReward::class, $reward);
        $this->assertEquals(GameReward::TYPE_BADGE, $reward->reward_type);
        $this->assertEquals(GameReward::STATUS_REDEEMED, $reward->status);
        $this->assertStringContainsString('Test Badge', $reward->description);
    }

    public function test_apply_probability_returns_boolean(): void
    {
        $service = app(PrizeService::class);
        $result = $service->applyProbability(50);
        $this->assertIsBool($result);
    }

    public function test_determine_tier_returns_null_for_empty_tiers(): void
    {
        $service = app(PrizeService::class);
        $this->assertNull($service->determineTier(100, []));
    }
}

