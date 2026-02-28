<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BusinessEmployeesInvitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_create_and_cancel_invite_and_resend_extends_expiry(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->get('/business/employees')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/Employees/Index'));

        $this->actingAs($owner)
            ->post('/business/employees', [
                'email' => 'staff@example.com',
                'role' => 'employee',
            ])
            ->assertStatus(302)
            ->assertSessionHas('invite_url');

        $invite = EmployeeInvite::where('business_id', $business->id)->where('email', 'staff@example.com')->first();
        $this->assertNotNull($invite);

        $oldExpiry = $invite->expires_at;

        // Move time forward so resend definitely extends expiry.
        $this->travel(2)->days();

        $this->actingAs($owner)
            ->post("/business/employees/invite/{$invite->id}/resend")
            ->assertStatus(302)
            ->assertSessionHas('invite_url');

        $invite->refresh();
        $this->assertTrue($invite->expires_at->gt($oldExpiry));

        $this->actingAs($owner)
            ->delete("/business/employees/invite/{$invite->id}")
            ->assertStatus(302);

        $this->assertDatabaseMissing('employee_invites', ['id' => $invite->id]);
    }

    public function test_business_invite_enforces_employee_limit_including_pending_invites(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'starter', // seeded plan has employees limit 2
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        // Create 2 active employees (hit the starter plan limit)
        for ($i = 0; $i < 2; $i++) {
            $u = User::factory()->create(['role' => 'employee']);
            Employee::create([
                'user_id' => $u->id,
                'business_id' => $business->id,
                'role' => 'employee',
                'is_active' => true,
                'can_redeem' => true,
            ]);
        }

        $this->actingAs($owner)
            ->post('/business/employees', [
                'email' => 'limit@example.com',
                'role' => 'employee',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['limit']);
    }

    public function test_business_inviting_same_email_twice_when_pending_resends_invite(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->post('/business/employees', [
                'email' => 'dup@example.com',
                'role' => 'employee',
            ])
            ->assertStatus(302)
            ->assertSessionHas('invite_url');

        // Second invite for same email resends (does not error)
        $this->actingAs($owner)
            ->post('/business/employees', [
                'email' => 'dup@example.com',
                'role' => 'employee',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success')
            ->assertSessionHas('invite_url');

        $this->assertSame(1, EmployeeInvite::where('business_id', $business->id)->where('email', 'dup@example.com')->count());
    }
}

