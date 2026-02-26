<?php

namespace Tests\Unit\Support;

use App\Support\BusinessCardQr;
use Tests\TestCase;

class BusinessCardQrTest extends TestCase
{
    public function test_constants_are_defined(): void
    {
        $this->assertSame('BIZCARD1', BusinessCardQr::CODE);
        $this->assertSame('Business Card QR', BusinessCardQr::NAME);
    }

    public function test_destination_url_returns_revenueqr_url(): void
    {
        $this->assertSame('https://revenueqr.com/', BusinessCardQr::destinationUrl());
    }
}
