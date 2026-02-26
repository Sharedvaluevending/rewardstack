<?php

namespace Tests\Unit\Notifications;

use App\Notifications\PortalOnboarding;
use Tests\TestCase;

class PortalOnboardingTest extends TestCase
{
    public function test_via_returns_database_channel(): void
    {
        $notification = new PortalOnboarding();
        $this->assertSame(['database'], $notification->via(new \stdClass()));
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $notification = new PortalOnboarding();
        $data = $notification->toArray(new \stdClass());

        $this->assertSame('Welcome! Tap here to learn how your portal works.', $data['message']);
        $this->assertSame('/portal', $data['action_url']);
        $this->assertSame('portal_onboarding', $data['type']);
        $this->assertSame('✨', $data['icon']);
    }
}
