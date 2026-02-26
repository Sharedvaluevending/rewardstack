<?php

namespace Tests\Unit\Notifications;

use App\Models\Business;
use App\Notifications\PartnershipEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnershipEndedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $business = Business::factory()->create();
        $notification = new PartnershipEnded($business);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_includes_partner_name(): void
    {
        $business = Business::factory()->create(['name' => 'Former Partner']);
        $notification = new PartnershipEnded($business);
        $data = $notification->toArray(new \stdClass());
        $this->assertStringContainsString('Former Partner', $data['message']);
        $this->assertSame('partnership_ended', $data['type']);
    }
}
