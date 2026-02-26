<?php

namespace Tests\Unit\Notifications;

use App\Models\Promotion;
use App\Notifications\PortalPunchCardCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPunchCardCompletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_via_returns_database_channel(): void
    {
        $promotion = Promotion::factory()->create();
        $notification = new PortalPunchCardCompleted($promotion);
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $promotion = Promotion::factory()->create(['name' => 'Buy 5 Get 1 Free']);
        $notification = new PortalPunchCardCompleted($promotion);
        $data = $notification->toArray(new \stdClass());

        $this->assertSame('Punch card completed: Buy 5 Get 1 Free', $data['message']);
        $this->assertSame('/portal/scans', $data['action_url']);
        $this->assertSame('punch_card_completed', $data['type']);
        $this->assertSame('✅', $data['icon']);
        $this->assertSame($promotion->id, $data['promotion_id']);
    }

    public function test_to_array_uses_fallback_when_promotion_name_empty(): void
    {
        $promotion = Promotion::factory()->create(['name' => '']);
        $notification = new PortalPunchCardCompleted($promotion);
        $data = $notification->toArray(new \stdClass());
        $this->assertSame('Punch card completed: Punch card', $data['message']);
    }
}
