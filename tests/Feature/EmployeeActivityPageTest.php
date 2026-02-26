<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeActivityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_activity_page_loads(): void
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

        $this->actingAs($employeeUser)
            ->get('/employee/activity')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Employee/Activity'));
    }
}

