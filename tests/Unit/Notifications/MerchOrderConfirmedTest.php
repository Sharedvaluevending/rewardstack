<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Order;
use App\Notifications\MerchOrderConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchOrderConfirmedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $order = Order::factory()->create();
        $notification = new MerchOrderConfirmed($order);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $business = Business::factory()->create(['name' => 'Store']);
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'order_number' => 'ORD-001',
            'total' => 49.99,
        ]);
        $notification = new MerchOrderConfirmed($order);
        $msg = $notification->toMail($business);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $msg);
        $this->assertStringContainsString('confirmed', $msg->subject);
    }
}
