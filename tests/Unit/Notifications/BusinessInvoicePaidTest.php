<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\BusinessInvoicePaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessInvoicePaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $business = Business::factory()->create();
        $notification = new BusinessInvoicePaid($business, ['amount' => 10, 'currency' => 'USD']);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $business = Business::factory()->create(['name' => 'Test Biz']);
        $notification = new BusinessInvoicePaid($business, [
            'amount' => 99.50,
            'currency' => 'USD',
            'period' => 'January 2026',
        ]);
        $msg = $notification->toMail($business);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $msg);
        $this->assertStringContainsString('Payment received', $msg->subject);
    }
}
