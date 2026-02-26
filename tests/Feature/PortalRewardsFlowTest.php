<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Business;
use App\Models\GamePlay;
use App\Models\GameReward;
use App\Models\QRCode;
use App\Models\User;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalRewardsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_index_loads_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get('/portal/rewards')
            ->assertStatus(200);
    }

    public function test_customer_can_claim_reward_and_qr_is_generated(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andReturn('qrcodes/reward.png');
        });

        $customer = User::factory()->create(['role' => 'customer']);

        $gamePlay = GamePlay::factory()->create([
            'user_id' => $customer->id,
        ]);

        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $gamePlay->business_id,
            'promotion_id' => null,
            'status' => GameReward::STATUS_AVAILABLE,
            'qr_image_path' => null,
            'expires_at' => now()->addDays(10),
        ]);

        $resp = $this->actingAs($customer)->post("/portal/rewards/{$reward->id}/claim");
        $resp->assertStatus(302);

        $reward->refresh();
        $this->assertSame(GameReward::STATUS_CLAIMED, $reward->status);
        $this->assertNotNull($reward->claimed_at);
        $this->assertSame('qrcodes/reward.png', $reward->qr_image_path);
    }

    public function test_claim_is_forbidden_for_other_user(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $a = User::factory()->create(['role' => 'customer']);
        $b = User::factory()->create(['role' => 'customer']);

        $gamePlay = GamePlay::factory()->create(['user_id' => $a->id]);
        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $a->id,
            'business_id' => $gamePlay->business_id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $this->actingAs($b)->post("/portal/rewards/{$reward->id}/claim")->assertStatus(403);
    }

    public function test_claim_returns_error_when_reward_not_available(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $customer = User::factory()->create(['role' => 'customer']);
        $gamePlay = GamePlay::factory()->create(['user_id' => $customer->id]);

        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $gamePlay->business_id,
            'status' => GameReward::STATUS_CLAIMED,
        ]);

        $this->actingAs($customer)
            ->from('/portal/rewards')
            ->post("/portal/rewards/{$reward->id}/claim")
            ->assertStatus(302)
            ->assertSessionHas('error', 'This reward is no longer available');
    }

    public function test_reward_detail_is_forbidden_for_other_user(): void
    {
        $a = User::factory()->create(['role' => 'customer']);
        $b = User::factory()->create(['role' => 'customer']);

        $gamePlay = GamePlay::factory()->create(['user_id' => $a->id]);
        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $a->id,
            'business_id' => $gamePlay->business_id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $this->actingAs($b)->get("/portal/rewards/{$reward->id}")->assertStatus(403);
    }

    public function test_reward_detail_loads_for_owner_and_generates_short_code_and_qr_with_original_design_logo_null(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $business = Business::factory()->create([
            'logo_path' => 'logos/test.png',
        ]);

        // Original QR design includes explicit "logo" key (null) => controller should NOT add business logo.
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'design' => [
                'logo' => null,
                'generated_path' => 'qrcodes/original.png',
            ],
        ]);

        $gamePlay = GamePlay::factory()->create([
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
        ]);

        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'qr_image_path' => null,
        ]);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')
                ->once()
                ->withArgs(function ($data, $design, $format) {
                    $this->assertStringContainsString('/r/', (string) $data);
                    $this->assertSame('png', $format);
                    $this->assertIsArray($design);
                    $this->assertArrayHasKey('logo', $design);
                    $this->assertNull($design['logo'], 'Should preserve original logo:null (no logo)');
                    $this->assertSame(500, $design['size']);
                    $this->assertArrayNotHasKey('generated_path', $design);
                    return true;
                })
                ->andReturn('qrcodes/reward-detail.png');
        });

        $this->actingAs($customer)
            ->get("/portal/rewards/{$reward->id}")
            ->assertStatus(200);

        $reward->refresh();
        $this->assertNotEmpty($reward->reward_code);
        $this->assertMatchesRegularExpression('/^UP-[A-Z0-9]{4}-[A-Z0-9]{4}$/', (string) $reward->reward_code);
        $this->assertSame('qrcodes/reward-detail.png', $reward->qr_image_path);
    }

    public function test_reward_detail_adds_business_logo_when_original_design_missing_logo_key(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $business = Business::factory()->create([
            'logo_path' => 'logos/test.png',
        ]);

        // Original design has NO "logo" key => controller should add business logo.
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'design' => [
                'generated_path' => 'qrcodes/original.png',
            ],
        ]);

        $gamePlay = GamePlay::factory()->create([
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
        ]);

        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'qr_image_path' => null,
        ]);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')
                ->once()
                ->withArgs(function ($data, $design, $format) {
                    $this->assertSame('png', $format);
                    $this->assertIsArray($design);
                    $this->assertArrayHasKey('logo', $design);
                    $this->assertIsArray($design['logo'], 'Should add business logo when missing from original design');
                    $this->assertArrayHasKey('url', $design['logo']);
                    return true;
                })
                ->andReturn('qrcodes/reward-detail-logo.png');
        });

        $this->actingAs($customer)->get("/portal/rewards/{$reward->id}")->assertStatus(200);

        $reward->refresh();
        $this->assertSame('qrcodes/reward-detail-logo.png', $reward->qr_image_path);
    }

    public function test_reward_detail_does_not_throw_if_qr_generation_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);

        $gamePlay = GamePlay::factory()->create([
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'qr_code_id' => $qrCode->id,
        ]);

        $reward = GameReward::factory()->create([
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'qr_image_path' => null,
        ]);

        $this->mock(QRGeneratorService::class, function ($mock) {
            $mock->shouldReceive('generateFile')->andThrow(new \Exception('boom'));
        });

        $this->actingAs($customer)->get("/portal/rewards/{$reward->id}")->assertStatus(200);
    }

    public function test_claim_by_code_accepts_short_code_and_claims_anonymous_reward(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $customer = User::factory()->create(['role' => 'customer']);
        $gamePlay = GamePlay::factory()->create(['user_id' => $customer->id]);

        $reward = GameReward::factory()->create([
            'reward_code' => 'ABCDEFGH',
            'game_play_id' => $gamePlay->id,
            'user_id' => null, // anonymous reward
            'business_id' => $gamePlay->business_id,
            'promotion_id' => null,
            'status' => GameReward::STATUS_AVAILABLE,
            'expires_at' => now()->addDays(10),
        ]);

        $resp = $this->actingAs($customer)->post('/portal/rewards/claim-by-code', [
            'reward_code' => 'ABCDEFGH',
        ]);
        $resp->assertStatus(302);

        $reward->refresh();
        $this->assertSame($customer->id, (int) $reward->user_id);
        $this->assertSame(GameReward::STATUS_CLAIMED, $reward->status);
    }

    public function test_claim_by_code_returns_error_when_code_not_found(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->from('/portal/rewards')
            ->post('/portal/rewards/claim-by-code', [
                'reward_code' => 'ABCDEFGH',
            ])
            ->assertStatus(302)
            ->assertSessionHas('error', 'Reward code not found. Please check the code and try again.');
    }

    public function test_claim_by_code_returns_error_when_claimed_by_another_account(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $a = User::factory()->create(['role' => 'customer']);
        $b = User::factory()->create(['role' => 'customer']);
        $gamePlay = GamePlay::factory()->create(['user_id' => $a->id]);

        GameReward::factory()->create([
            'reward_code' => 'ABCDEFGH',
            'game_play_id' => $gamePlay->id,
            'user_id' => $a->id,
            'business_id' => $gamePlay->business_id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        $this->actingAs($b)
            ->from('/portal/rewards')
            ->post('/portal/rewards/claim-by-code', [
                'reward_code' => 'ABCDEFGH',
            ])
            ->assertStatus(302)
            ->assertSessionHas('error', 'This reward code has already been claimed by another account.');
    }

    public function test_claim_by_code_claims_reward_for_same_user_and_increments_stats_only_when_anonymous(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $customer = User::factory()->create(['role' => 'customer', 'total_rewards_won' => 0]);
        $gamePlay = GamePlay::factory()->create(['user_id' => $customer->id]);

        $reward = GameReward::factory()->create([
            'reward_code' => 'ABCDEFGH',
            'game_play_id' => $gamePlay->id,
            'user_id' => $customer->id,
            'business_id' => $gamePlay->business_id,
            'status' => GameReward::STATUS_AVAILABLE,
        ]);

        // Creating an AVAILABLE reward for a user increments total_rewards_won via GameReward::created hook.
        $customer->refresh();
        $this->assertSame(1, (int) $customer->total_rewards_won);

        $this->actingAs($customer)
            ->from('/portal/rewards')
            ->post('/portal/rewards/claim-by-code', [
                'reward_code' => 'ABCDEFGH',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success', 'Reward claimed successfully! You can now redeem it with staff.');

        $reward->refresh();
        $this->assertSame(GameReward::STATUS_CLAIMED, $reward->status);

        $customer->refresh();
        $this->assertSame(1, (int) $customer->total_rewards_won, 'Should not increment stats again when reward already belongs to user');
    }
}

