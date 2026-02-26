<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Order;
use App\Notifications\MerchOrderFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchOrderFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_mail_returns_mail_message_with_order_details(): void
    {
        $business = Business::factory()->create(['name' => 'Test Shop']);
        $order = Order::factory()->create([
            'business_id' => $business->id,
            'order_number' => 'ORD-123',
        ]);

        $notification = new MerchOrderFailed($order, 'Fulfillment error');
        $mail = $notification->toMail($business->owner);

        $this->assertStringContainsString('ORD-123', $mail->subject);
        $this->assertStringContainsString('/business/merch/orders', $mail->actionUrl);
    }
}
