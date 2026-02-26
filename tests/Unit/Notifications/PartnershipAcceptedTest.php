<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\PartnershipAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnershipAcceptedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create();
        $notification = new PartnershipAccepted($business);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_includes_partner_name(): void
    {
        $business = Business::factory()->create(['name' => 'Partner Co']);
        $notification = new PartnershipAccepted($business);
        $data = $notification->toArray(new \stdClass());
        $this->assertStringContainsString('Partner Co', $data['message']);
        $this->assertSame('partnership_accepted', $data['type']);
    }
}
