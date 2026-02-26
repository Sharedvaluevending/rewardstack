<?php

namespace Tests\Unit\Models;

use App\Models\ReferralCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralCommissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending', ReferralCommission::STATUS_PENDING);
        $this->assertSame('approved', ReferralCommission::STATUS_APPROVED);
        $this->assertSame('processing', ReferralCommission::STATUS_PROCESSING);
        $this->assertSame('paid', ReferralCommission::STATUS_PAID);
        $this->assertSame('cancelled', ReferralCommission::STATUS_CANCELLED);
    }
}
