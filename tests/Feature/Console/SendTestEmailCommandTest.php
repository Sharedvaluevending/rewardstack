<?php

namespace Tests\Feature\Console;

use App\Mail\TestEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_test_command_sends_mailable_and_outputs_sender_info(): void
    {
        Mail::fake();

        config([
            'mail.from.name' => 'RewardStack',
            'mail.from.address' => 'from@example.com',
        ]);

        $this->artisan('email:test', [
            'to' => 'to@example.com',
            '--subject' => 'Hello Subject',
        ])
            ->expectsOutputToContain('Sent test email to to@example.com')
            ->assertExitCode(0);

        Mail::assertSent(TestEmail::class, function (TestEmail $mail) {
            return $mail->hasTo('to@example.com') && $mail->subjectLine === 'Hello Subject';
        });
    }
}

