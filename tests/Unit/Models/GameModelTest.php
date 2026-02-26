<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_key_name_is_slug(): void
    {
        $this->assertSame('slug', (new Game)->getRouteKeyName());
    }

    public function test_game_type_constants_are_defined(): void
    {
        $this->assertSame('memory_match', Game::TYPE_MEMORY_MATCH);
        $this->assertSame('word_search', Game::TYPE_WORD_SEARCH);
        $this->assertSame('snake', Game::TYPE_SNAKE);
    }

    public function test_game_types_returns_expected_keys(): void
    {
        $types = Game::gameTypes();
        $this->assertArrayHasKey(Game::TYPE_MEMORY_MATCH, $types);
        $this->assertArrayHasKey(Game::TYPE_SNAKE, $types);
        $this->assertStringContainsString('Memory', $types[Game::TYPE_MEMORY_MATCH]);
    }

    public function test_tiers_returns_expected_keys(): void
    {
        $tiers = Game::tiers();
        $this->assertArrayHasKey(Game::TIER_BASIC, $tiers);
        $this->assertArrayHasKey(Game::TIER_PRO, $tiers);
        $this->assertArrayHasKey(Game::TIER_PREMIUM, $tiers);
    }
}
