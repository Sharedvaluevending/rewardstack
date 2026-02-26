<?php

namespace App\Support;

class OnboardingQr
{
    public const CODE = 'JOINRQR1';
    public const NAME = 'Portal Join Flyer QR';

    public static function destinationUrl(): string
    {
        return url('/portal/join?from=onboarding_qr');
    }
}
