<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Game;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GameServiceFunOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fun_only_when_no_promotions(): void
    {
        $business = Business::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => null]);
        $game = Game::factory()->create();

        QRCodeGame::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
        ]);

        $service = app(GameService::class);
        $res = $service->getFunOnlyStatus($qr, $game, null);

        $this->assertTrue($res['fun_only']);
        $this->assertStringContainsString('Just for fun', $res['reason']);
    }

    public function test_fun_only_false_when_active_promo_and_user_below_limit(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'ends_at' => now()->addDay(),
            'rules' => ['max_redemptions_per_user' => 1],
        ]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $game = Game::factory()->create();

        QRCodeGame::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
            'promotion_id' => $promo->id,
            'tier_rewards' => [$promo->id],
        ]);

        $service = app(GameService::class);
        $res = $service->getFunOnlyStatus($qr, $game, $user);

        $this->assertFalse($res['fun_only']);
        $this->assertNull($res['reason']);
    }

    public function test_fun_only_true_when_user_hit_limit(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'ends_at' => now()->addDay(),
            'rules' => ['max_redemptions_per_user' => 1],
        ]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $game = Game::factory()->create();

        QRCodeGame::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
            'promotion_id' => $promo->id,
            'tier_rewards' => [$promo->id],
        ]);

        // Simulate user already redeemed
        \App\Models\Redemption::create([
            'promotion_id' => $promo->id,
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'customer_user_id' => $user->id,
            'customer_identifier' => 'cust-'.$user->id,
            'discount_amount' => 5,
            'final_amount' => 5,
            'redeemed_at' => now(),
        ]);

        $service = app(GameService::class);
        $res = $service->getFunOnlyStatus($qr, $game, $user);

        $this->assertTrue($res['fun_only']);
        $this->assertStringContainsString('max rewards', $res['reason']);
    }

    public function test_fun_only_true_when_user_has_unredeemed_reward_prompts_to_redeem_first(): void
    {
        Notification::fake();
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'ends_at' => now()->addDay(),
            'rules' => ['max_redemptions_per_user' => 1],
        ]);
        $qr = QRCode::factory()->create(['business_id' => $business->id, 'promotion_id' => $promo->id]);
        $game = Game::factory()->create();

        QRCodeGame::create([
            'qr_code_id' => $qr->id,
            'game_id' => $game->id,
            'business_id' => $business->id,
            'is_active' => true,
            'win_mode' => QRCodeGame::WIN_MODE_RANDOM,
            'promotion_id' => $promo->id,
            'tier_rewards' => [$promo->id],
        ]);

        // Simulate user won but has not redeemed
        GameReward::factory()->create([
            'user_id' => $user->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $service = app(GameService::class);
        $res = $service->getFunOnlyStatus($qr, $game, $user);

        $this->assertTrue($res['fun_only']);
        $this->assertStringContainsString('Redeem your existing reward in My Scans first', $res['reason']);
    }
}

