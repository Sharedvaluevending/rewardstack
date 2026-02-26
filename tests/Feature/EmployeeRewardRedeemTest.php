<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\GameReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRewardRedeemTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_redeem_game_reward(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
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
            'status' => GameReward::STATUS_CLAIMED,
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/redeem/reward/' . $reward->reward_code, [])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $reward->refresh();
        $this->assertSame(GameReward::STATUS_REDEEMED, $reward->status);
    }

    public function test_employee_cannot_redeem_reward_for_other_business(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

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

        $reward = GameReward::factory()->create([
            'business_id' => $b2->id,
            'status' => GameReward::STATUS_CLAIMED,
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/redeem/reward/' . $reward->reward_code, [])
            ->assertStatus(404);
    }
}

