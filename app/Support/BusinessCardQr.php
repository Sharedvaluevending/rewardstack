<?php

namespace App\Support;

class BusinessCardQr
{
    public const CODE = 'BIZCARD1';
    public const NAME = 'Business Card QR';

    public static function destinationUrl(): string
    {
        return 'https://revenueqr.com/';
    }
}
