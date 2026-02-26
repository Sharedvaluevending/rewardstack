<?php

namespace Tests\Unit\Services;

use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\QRGeneratorService;
use App\Services\UserPromoTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserPromoTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createServiceWithMockedQr(): UserPromoTokenService
    {
        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')
            ->andReturn('tokens/test-qr.png');
        $this->app->instance(QRGeneratorService::class, $qrMock);
        return app(UserPromoTokenService::class);
    }

    public function test_ensure_returns_null_for_non_customer_user(): void
    {
        $service = $this->createServiceWithMockedQr();
        $user = User::factory()->create(['role' => 'business']);
        $qrCode = QRCode::factory()->promotion()->create();
        $qrCode->load('promotion');

        $result = $service->ensure($user, $qrCode);
        $this->assertNull($result);
    }

    public function test_ensure_returns_null_when_qr_code_has_no_promotion(): void
    {
        $service = $this->createServiceWithMockedQr();
        $user = User::factory()->create(['role' => 'customer']);
        $qrCode = QRCode::factory()->create(['promotion_id' => null]);

        $result = $service->ensure($user, $qrCode);
        $this->assertNull($result);
    }

    public function test_ensure_creates_new_token_for_customer_with_no_existing_token(): void
    {
        $service = $this->createServiceWithMockedQr();
        $user = User::factory()->create(['role' => 'customer']);
        $promo = Promotion::factory()->create();
        $qrCode = QRCode::factory()->promotion()->create(['promotion_id' => $promo->id]);
        $qrCode->load('promotion');

        $token = $service->ensure($user, $qrCode);
        $this->assertInstanceOf(UserPromoToken::class, $token);
        $this->assertSame($user->id, $token->user_id);
        $this->assertSame($qrCode->id, $token->qr_code_id);
        $this->assertSame($qrCode->promotion_id, $token->promotion_id);
        $this->assertNull($token->redeemed_at);
        $this->assertMatchesRegularExpression('/^UP-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $token->code);
    }

    public function test_ensure_reuses_existing_token_when_not_stacking(): void
    {
        $service = $this->createServiceWithMockedQr();
        $user = User::factory()->create(['role' => 'customer']);
        $promo = Promotion::factory()->create(['rules' => []]);
        $qrCode = QRCode::factory()->promotion()->create(['promotion_id' => $promo->id]);
        $qrCode->load('promotion');

        $first = $service->ensure($user, $qrCode);
        $second = $service->ensure($user, $qrCode);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UserPromoToken::where('user_id', $user->id)->where('qr_code_id', $qrCode->id)->count());
    }

    public function test_ensure_merch_referral_always_uses_limit_one(): void
    {
        $service = $this->createServiceWithMockedQr();
        $user = User::factory()->create(['role' => 'customer']);
        $qrCode = QRCode::factory()->create([
            'type' => 'merch_referral',
            'promotion_id' => Promotion::factory()->create(['rules' => ['portal_multiple_scans' => true, 'max_redemptions_per_user' => 5]])->id,
        ]);
        $qrCode->load('promotion');

        $first = $service->ensure($user, $qrCode);
        $second = $service->ensure($user, $qrCode);

        $this->assertSame($first->id, $second->id);
    }
}
