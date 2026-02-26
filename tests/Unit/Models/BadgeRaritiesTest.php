<?php

namespace Tests\Unit\Models;

use App\Models\Badge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeRaritiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rarities_returns_expected_keys_and_structure(): void
    {
        $rarities = Badge::rarities();
        $this->assertIsArray($rarities);
        $this->assertArrayHasKey(Badge::RARITY_COMMON, $rarities);
        $this->assertArrayHasKey(Badge::RARITY_LEGENDARY, $rarities);
        $common = $rarities[Badge::RARITY_COMMON];
        $this->assertArrayHasKey('name', $common);
        $this->assertArrayHasKey('color', $common);
        $this->assertSame('Common', $common['name']);
    }
}
