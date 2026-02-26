<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use App\Services\RewardCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_unique_code_returns_format_up_xxxx_xxxx(): void
    {
        $service = new RewardCodeService();
        $code = $service->generateUniqueCode();

        $this->assertMatchesRegularExpression('/^UP-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code);
        $this->assertSame(12, strlen($code)); // UP- (3) + 4 + - (1) + 4
        $this->assertStringStartsWith('UP-', $code);
    }

    public function test_generate_unique_code_avoids_o_and_i_in_alphabet(): void
    {
        $service = new RewardCodeService();
        $seen = [];
        for ($i = 0; $i < 20; $i++) {
            $code = $service->generateUniqueCode();
            $parts = explode('-', $code);
            $this->assertCount(3, $parts);
            foreach (str_split($parts[1] . $parts[2]) as $char) {
                $this->assertFalse(str_contains('OI01', $char), "Code should not contain O, I, 0, or 1: {$code}");
            }
            $seen[] = $code;
        }
        $this->assertCount(20, array_unique($seen));
    }

    public function test_generate_unique_code_does_not_return_existing_user_promo_token_code(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();
        $qr = QRCode::factory()->create(['business_id' => $business->id]);
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-ABCD-2345',
        ]);
        $service = new RewardCodeService();

        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = $service->generateUniqueCode();
            $this->assertNotSame('UP-ABCD-2345', $code);
            $codes[] = $code;
        }
        $this->assertCount(10, array_unique($codes));
    }

    public function test_generate_unique_code_does_not_return_existing_game_reward_code(): void
    {
        $reward = GameReward::factory()->create();
        $existingCode = $reward->reward_code;
        $service = new RewardCodeService();

        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $code = $service->generateUniqueCode();
            $this->assertNotSame($existingCode, $code);
            $codes[] = $code;
        }
        $this->assertCount(5, array_unique($codes));
    }
}
