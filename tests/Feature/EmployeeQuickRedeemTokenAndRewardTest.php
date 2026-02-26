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

class EmployeeQuickRedeemTokenAndRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_quick_redeem_token_accepts_code_without_dashes(): void
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
            'rules' => [
                // Keep redemption rules permissive for this view test.
                'max_redemptions_per_user' => 0,
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
            'code' => 'UP-ABCD-EFGH',
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/token/UPABCDEFGH')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/QuickRedeem')
                    ->where('prefillCustomerPromoCode', $token->code)
            );
    }

    public function test_employee_quick_redeem_token_blocks_wrong_business(): void
    {
        $b1 = Business::factory()->create();
        $b1->owner->update(['role' => 'business']);

        $b2 = Business::factory()->create();
        $b2->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $b1->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true]);
        $qr = QRCode::factory()->promotion()->create(['business_id' => $b2->id, 'promotion_id' => $promo->id, 'is_active' => true]);
        $token = UserPromoToken::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $b2->id,
            'code' => 'UP-WRNG-BIZZ',
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/token/' . $token->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/WrongBusiness')
                    ->where('qrBusiness', $b2->name)
                    ->where('yourBusiness', $b1->name)
            );
    }

    public function test_employee_quick_redeem_reward_shows_already_redeemed_message(): void
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

        $reward = GameReward::factory()->redeemed()->create([
            'business_id' => $business->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/reward/' . $reward->reward_code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/QuickRedeemReward')
                    ->where('canRedeem', false)
                    ->where('redeemMessage', 'This reward has already been redeemed.')
            );
    }

    public function test_redeem_reward_endpoint_rejects_already_redeemed_reward(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

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

        $reward = GameReward::factory()->redeemed()->create([
            'business_id' => $business->id,
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/redeem/reward/' . $reward->reward_code, [])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This reward cannot be redeemed',
            ]);
    }
}

