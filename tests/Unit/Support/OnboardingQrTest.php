<?php

namespace Tests\Unit\Support;

use App\Support\OnboardingQr;
use Tests\TestCase;

class OnboardingQrTest extends TestCase
{
    public function test_constants_are_defined(): void
    {
        $this->assertSame('JOINRQR1', OnboardingQr::CODE);
        $this->assertSame('Portal Join Flyer QR', OnboardingQr::NAME);
    }

    public function test_destination_url_returns_portal_join_url(): void
    {
        $url = OnboardingQr::destinationUrl();
        $this->assertStringContainsString('/portal/join', $url);
        $this->assertStringContainsString('from=onboarding_qr', $url);
    }
}
