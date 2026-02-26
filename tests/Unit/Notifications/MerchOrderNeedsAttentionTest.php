<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Models\Order;
use App\Notifications\MerchOrderNeedsAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchOrderNeedsAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $order = Order::factory()->create();
        $notification = new MerchOrderNeedsAttention($order, 'Payment failed');
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }
}
