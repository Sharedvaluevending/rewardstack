<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeInviteAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_accept_page_renders_and_accept_creates_employee_and_logs_in(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create();
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $invite = EmployeeInvite::createInvite(
            businessId: (int) $business->id,
            email: 'newstaff@example.com',
            invitedBy: (int) $owner->id,
            role: 'manager'
        );

        $this->get('/employee/accept/' . $invite->token)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Auth/AcceptInvite')
                    ->where('invite.email', 'newstaff@example.com')
                    ->where('invite.business_name', $business->name)
            );

        $this->post('/employee/accept/' . $invite->token, [
            'name' => 'Staff Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/employee/redeem');

        $user = User::where('email', 'newstaff@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('employee', $user->role);

        $this->assertDatabaseHas('employees', [
            'business_id' => $business->id,
            'user_id' => $user->id,
        ]);

        $employee = Employee::where('business_id', $business->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($employee);
        $this->assertTrue((bool) $employee->can_view_analytics);

        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);
        $this->assertSame($user->id, $invite->user_id);
    }

    public function test_expired_invite_shows_expired_page(): void
    {
        $business = Business::factory()->create();
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $invite = EmployeeInvite::createInvite(
            businessId: (int) $business->id,
            email: 'expired@example.com',
            invitedBy: (int) $owner->id,
            role: 'employee'
        );

        $invite->update(['expires_at' => now()->subMinute()]);

        $this->get('/employee/accept/' . $invite->token)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Auth/InviteExpired'));
    }
}

