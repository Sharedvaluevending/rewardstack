<?php

namespace Tests\Unit\Models;

use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_constants_are_defined(): void
    {
        $this->assertSame('high_score', Tournament::TYPE_HIGH_SCORE);
        $this->assertSame('cumulative', Tournament::TYPE_CUMULATIVE);
        $this->assertSame('challenge', Tournament::TYPE_CHALLENGE);
        $this->assertSame('bracket', Tournament::TYPE_BRACKET);
        $this->assertSame('time_attack', Tournament::TYPE_TIME_ATTACK);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('draft', Tournament::STATUS_DRAFT);
        $this->assertSame('upcoming', Tournament::STATUS_UPCOMING);
        $this->assertSame('registration', Tournament::STATUS_REGISTRATION);
        $this->assertSame('active', Tournament::STATUS_ACTIVE);
        $this->assertSame('completed', Tournament::STATUS_COMPLETED);
        $this->assertSame('cancelled', Tournament::STATUS_CANCELLED);
    }
}
