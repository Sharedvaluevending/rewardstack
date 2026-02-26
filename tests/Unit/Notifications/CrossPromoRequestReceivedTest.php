<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\CrossPromoRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoRequestReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create();
        $notification = new CrossPromoRequestReceived($business, 'Summer Deal');
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_includes_requester_and_promo_name(): void
    {
        $business = Business::factory()->create(['name' => 'Acme']);
        $notification = new CrossPromoRequestReceived($business, 'Summer Deal');
        $data = $notification->toArray(new \stdClass());
        $this->assertStringContainsString('Acme', $data['message']);
        $this->assertStringContainsString('Summer Deal', $data['message']);
        $this->assertSame('cross_promo_request', $data['type']);
    }
}
