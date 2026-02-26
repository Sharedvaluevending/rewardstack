<?php

namespace Tests\Unit\Mail;

use App\Mail\EmployeeInvitation;
use App\Models\Business;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_subject_includes_business_name(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id, 'name' => 'Acme Co']);
        $invite = EmployeeInvite::create([
            'business_id' => $business->id,
            'email' => 'employee@example.com',
            'invited_by' => $user->id,
            'role' => 'employee',
            'token' => EmployeeInvite::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $mailable = new EmployeeInvitation($invite);
        $envelope = $mailable->envelope();

        $this->assertStringContainsString('Acme Co', $envelope->subject);
        $this->assertStringContainsString('invited', $envelope->subject);
    }

    public function test_content_uses_markdown_view(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['user_id' => $user->id]);
        $invite = EmployeeInvite::create([
            'business_id' => $business->id,
            'email' => 'emp@example.com',
            'invited_by' => $user->id,
            'role' => 'employee',
            'token' => EmployeeInvite::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $mailable = new EmployeeInvitation($invite);
        $content = $mailable->content();

        $this->assertSame('emails.employee-invitation', $content->markdown);
    }
}
