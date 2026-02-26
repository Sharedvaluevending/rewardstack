<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitation;
use App\Models\Business;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmployeeInviteTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'business']);
        $this->business = Business::factory()->create([
            'user_id' => $this->owner->id,
            'subscription_tier' => 'pro', // Ensure pro tier to allow employees
            'is_testing_account' => true,
        ]);
    }

    /** @test */
    public function inviting_employee_sends_email()
    {
        Mail::fake();

        $this->actingAs($this->owner);

        $response = $this->post(route('business.employees.store'), [
            'email' => 'newstaff@example.com',
            'role' => 'staff',
        ]);

        $response->assertRedirect(route('business.employees.index'));
        $response->assertSessionHas('success');

        Mail::assertSent(EmployeeInvitation::class, function ($mail) {
            return $mail->hasTo('newstaff@example.com') &&
                   $mail->invite->business_id === $this->business->id;
        });

        $this->assertDatabaseHas('employee_invites', [
            'email' => 'newstaff@example.com',
            'business_id' => $this->business->id,
        ]);
    }

    /** @test */
    public function resending_invite_sends_email()
    {
        Mail::fake();

        $invite = EmployeeInvite::createInvite(
            $this->business->id,
            'pending@example.com',
            $this->owner->id
        );

        $this->actingAs($this->owner);

        $response = $this->post(route('business.employees.invite.resend', $invite));

        $response->assertSessionHas('success');

        Mail::assertSent(EmployeeInvitation::class, function ($mail) use ($invite) {
            return $mail->hasTo('pending@example.com') &&
                   $mail->invite->id === $invite->id;
        });
    }
}
