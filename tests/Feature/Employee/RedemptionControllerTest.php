<?php

namespace Tests\Feature\Employee;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Redemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RedemptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_redeem_index_renders_with_today_stats(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'can_redeem' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/employee/redeem')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/Redeem')
                    ->has('employee')
                    ->has('business')
                    ->has('todayRedemptions')
                    ->has('todayStats')
            );
    }

    public function test_employee_without_employer_sees_no_employer_page(): void
    {
        $user = User::factory()->create(['role' => 'employee']);

        $this->actingAs($user)
            ->get('/employee/redeem')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Employee/NoEmployer')
            );
    }
}
