<?php

namespace Tests\Unit\Console;

use App\Mail\TestEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_test_email_sends_to_recipient(): void
    {
        Mail::fake();

        $exitCode = Artisan::call('email:test', ['to' => 'recipient@example.com']);

        $this->assertSame(0, $exitCode);
        Mail::assertSent(TestEmail::class);
    }

    public function test_send_test_email_with_subject_override(): void
    {
        Mail::fake();

        Artisan::call('email:test', [
            'to' => 'user@example.com',
            '--subject' => 'Custom subject',
        ]);

        Mail::assertSent(TestEmail::class, function ($mail) {
            return $mail->subjectLine === 'Custom subject';
        });
    }
}
