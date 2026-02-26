<?php

namespace Tests\Unit\Models;

use App\Models\UserMerchUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMerchUnlockModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending', UserMerchUnlock::STATUS_PENDING);
        $this->assertSame('active', UserMerchUnlock::STATUS_ACTIVE);
        $this->assertSame('expired', UserMerchUnlock::STATUS_EXPIRED);
    }
}
