<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Order;
use App\Notifications\MerchOrderShipped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchOrderShippedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $order = Order::factory()->create();
        $notification = new MerchOrderShipped($order);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }

    public function test_to_mail_returns_mail_message(): void
    {
        $business = Business::factory()->create();
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'order_number' => 'ORD-002',
            'tracking_number' => '1Z999',
        ]);
        $notification = new MerchOrderShipped($order);
        $msg = $notification->toMail($business);
        $this->assertInstanceOf(\Illuminate\Notifications\Messages\MailMessage::class, $msg);
        $this->assertStringContainsString('shipped', $msg->subject);
    }
}
