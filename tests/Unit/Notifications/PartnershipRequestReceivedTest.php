<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\PartnershipRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnershipRequestReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create(['name' => 'Acme Corp']);
        $notification = new PartnershipRequestReceived($business);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_includes_requester_name_and_action_url(): void
    {
        $business = Business::factory()->create(['name' => 'Acme Corp']);
        $notification = new PartnershipRequestReceived($business);
        $data = $notification->toArray(new \stdClass());
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Acme Corp', $data['message']);
        $this->assertSame('/business/partnerships', $data['action_url']);
        $this->assertSame('partnership_request', $data['type']);
    }
}
