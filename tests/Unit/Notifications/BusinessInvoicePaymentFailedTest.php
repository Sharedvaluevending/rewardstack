<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\BusinessInvoicePaymentFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessInvoicePaymentFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_mail_channel(): void
    {
        $business = Business::factory()->create();
        $notification = new BusinessInvoicePaymentFailed($business, []);
        $this->assertSame(['mail'], $notification->via(new \stdClass()));
    }
}
