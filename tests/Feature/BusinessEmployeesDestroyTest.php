<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessEmployeesDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_deactivate_employee(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        $employee = Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $this->actingAs($owner)
            ->delete('/business/employees/' . $employee->id)
            ->assertStatus(302);

        $employee->refresh();
        $this->assertFalse((bool) $employee->is_active);
    }
}

