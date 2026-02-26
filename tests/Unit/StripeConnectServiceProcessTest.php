<?php

namespace Tests\Unit;

use App\Services\StripeConnectService;
use Tests\TestCase;

class StripeConnectServiceProcessTest extends TestCase
{
    public function test_process_payouts_returns_message_when_disabled(): void
    {
        config(['stripe.connect.enabled' => false]);
        $service = app(StripeConnectService::class);
        $res = $service->processAutomaticPayouts();
        $this->assertSame('Stripe Connect not enabled', $res['message']);
        $this->assertSame(0, $res['processed']);
    }
}

