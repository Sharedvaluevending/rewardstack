<?php

namespace Tests\Unit\Models;

use App\Models\MerchUnlockRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchUnlockRuleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlock_type_constants_are_defined(): void
    {
        $this->assertSame('game', MerchUnlockRule::UNLOCK_GAME);
        $this->assertSame('game_pack', MerchUnlockRule::UNLOCK_GAME_PACK);
        $this->assertSame('double_rewards', MerchUnlockRule::UNLOCK_DOUBLE_REWARDS);
        $this->assertSame('exclusive_badge', MerchUnlockRule::UNLOCK_EXCLUSIVE_BADGE);
    }

    public function test_duration_constants_are_defined(): void
    {
        $this->assertSame('permanent', MerchUnlockRule::DURATION_PERMANENT);
        $this->assertSame('days', MerchUnlockRule::DURATION_DAYS);
        $this->assertSame('until_date', MerchUnlockRule::DURATION_UNTIL_DATE);
    }
}
