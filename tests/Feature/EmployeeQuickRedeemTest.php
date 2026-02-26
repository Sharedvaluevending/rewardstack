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

class EmployeeQuickRedeemTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_redeem_dashboard_requires_employment(): void
    {
        $employeeUser = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employeeUser)
            ->get('/employee/redeem')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/NoEmployer'));
    }

    public function test_employee_redeem_dashboard_loads_for_employee(): void
    {
        $business = Business::factory()->create();
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $this->actingAs($employeeUser)
            ->get('/employee/redeem')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Redeem'));
    }

    public function test_quick_redeem_redirects_reward_code_to_reward(): void
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

        $reward = GameReward::factory()->create([
            'business_id' => $business->id,
            'status' => GameReward::STATUS_AVAILABLE,
            'reward_code' => 'UP-TEST-0002',
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/' . $reward->reward_code)
            ->assertRedirect('/redeem/reward/' . $reward->reward_code);
    }

    public function test_quick_redeem_redirects_up_code_to_token_lookup(): void
    {
        $business = Business::factory()->create();
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
        $qr = QRCode::factory()->promotion()->create(['business_id' => $business->id, 'promotion_id' => $promo->id, 'is_active' => true]);
        $token = UserPromoToken::create([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-0001',
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/' . $token->code)
            ->assertRedirect('/redeem/token/' . $token->code);
    }

    public function test_quick_redeem_blocks_wrong_business(): void
    {
        $b1 = Business::factory()->create();
        $b1->owner->update(['role' => 'business']);
        $b2 = Business::factory()->create();
        $b2->owner->update(['role' => 'business']);

        $promo = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true]);
        $qr = QRCode::factory()->promotion()->create(['business_id' => $b1->id, 'promotion_id' => $promo->id, 'is_active' => true]);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $b2->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $this->actingAs($employeeUser)
            ->get('/redeem/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/WrongBusiness')
                    ->where('qrBusiness', $b1->name)
                    ->where('yourBusiness', $b2->name)
            );
    }
}

