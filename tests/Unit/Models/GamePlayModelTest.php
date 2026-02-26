<?php

namespace Tests\Unit\Models;

use App\Models\GamePlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_constants_are_defined(): void
    {
        $this->assertSame('win', GamePlay::RESULT_WIN);
        $this->assertSame('lose', GamePlay::RESULT_LOSE);
        $this->assertSame('complete', GamePlay::RESULT_COMPLETE);
    }

    public function test_tier_constants_are_defined(): void
    {
        $this->assertSame('gold', GamePlay::TIER_GOLD);
        $this->assertSame('silver', GamePlay::TIER_SILVER);
        $this->assertSame('bronze', GamePlay::TIER_BRONZE);
        $this->assertSame('none', GamePlay::TIER_NONE);
    }
}
