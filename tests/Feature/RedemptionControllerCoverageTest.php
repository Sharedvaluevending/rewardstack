<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\GameReward;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * RedemptionController coverage — safe tests, no Stripe/Printful.
 */
class RedemptionControllerCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_reward_route_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/r/SOME-CODE');

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_short_token_route_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/t/UP-XXXX-XXXX');

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_short_reward_route_renders_redeem_page_when_authenticated_with_valid_reward(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $reward = GameReward::factory()->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'reward_code' => 'RS-TEST-1234',
        ]);

        $response = $this->actingAs($employeeUser)
            ->get('/r/' . $reward->reward_code);

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/QuickRedeemReward')
                ->where('reward.reward_code', $reward->reward_code)
                ->where('canRedeem', true)
            );
    }

    public function test_short_token_route_renders_redeem_page_when_authenticated_with_valid_token(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create(['business_id' => $business->id, 'is_active' => true]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $token = UserPromoToken::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-SHORT-TOKN',
        ]);

        $response = $this->actingAs($employeeUser)
            ->get('/t/' . $token->code);

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/QuickRedeem')
                ->where('prefillCustomerPromoCode', $token->code)
            );
    }

    public function test_redeem_reward_success_updates_status(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $reward = GameReward::factory()->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'reward_code' => 'RS-SUCC-9999',
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($employeeUser)
            ->postJson(route('redeem.reward.process', ['rewardCode' => $reward->reward_code]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reward redeemed successfully',
            ]);

        $reward->refresh();
        $this->assertSame(GameReward::STATUS_REDEEMED, $reward->status);
        $this->assertNotNull($reward->redeemed_at);
    }

    public function test_redeem_reward_rejects_expired_reward(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        // AVAILABLE but expires_at in past - hits the "expired" check path
        $reward = GameReward::factory()->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'reward_code' => 'RS-EXPR-0001',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($employeeUser)
            ->postJson(route('redeem.reward.process', ['rewardCode' => $reward->reward_code]));

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This reward has expired and can no longer be redeemed',
            ]);
    }

    public function test_employee_redeem_index_shows_today_stats(): void
    {
        $business = Business::factory()->create();
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $response = $this->actingAs($employeeUser)
            ->get(route('employee.redeem'));

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Redeem')
                ->has('todayRedemptions')
                ->has('todayStats')
                ->where('todayStats.total', 0)
                ->where('todayStats.savings', 0)
            );
    }

    public function test_token_info_returns_punch_card_progress_for_valid_token(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'discount_type' => 'percentage',
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $token = UserPromoToken::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-TOKN-INFO1',
        ]);

        $response = $this->actingAs($employeeUser)
            ->getJson(route('employee.token.info', ['tokenCode' => $token->code]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'token' => [
                    'code' => $token->code,
                ],
            ]);
    }

    public function test_token_info_returns_buy_x_get_y_fields_for_buy_x_get_y_promo(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
            'discount_type' => 'buy_x_get_y',
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'rules' => [
                'buy_item_prices' => [10.00, 12.00],
                'get_item_prices' => [8.00],
            ],
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $token = UserPromoToken::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-BUYX-001',
        ]);

        $response = $this->actingAs($employeeUser)
            ->getJson(route('employee.token.info', ['tokenCode' => $token->code]));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('promotion.discount_type', 'buy_x_get_y')
            ->assertJsonPath('promotion.buy_quantity', 2)
            ->assertJsonPath('promotion.get_quantity', 1);
        $data = $response->json();
        $this->assertEquals([10, 12], $data['promotion']['rules']['buy_item_prices']);
        $this->assertEquals([8], $data['promotion']['rules']['get_item_prices']);
    }

    public function test_token_info_returns_404_for_invalid_token(): void
    {
        $business = Business::factory()->create(['is_testing_account' => true]);
        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $response = $this->actingAs($employeeUser)
            ->getJson(route('employee.token.info', ['tokenCode' => 'UP-NONE-XIST']));

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Customer promo code not found',
            ]);
    }
}
