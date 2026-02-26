<?php

namespace Tests\Unit\Models;

use App\Models\GamePack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePackModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_constants_are_defined(): void
    {
        $this->assertSame('subscription', GamePack::TYPE_SUBSCRIPTION);
        $this->assertSame('one_time', GamePack::TYPE_ONE_TIME);
    }

    public function test_category_constants_are_defined(): void
    {
        $this->assertSame('starter', GamePack::CATEGORY_STARTER);
        $this->assertSame('pro', GamePack::CATEGORY_PRO);
        $this->assertSame('family', GamePack::CATEGORY_FAMILY);
        $this->assertSame('fitness', GamePack::CATEGORY_FITNESS);
        $this->assertSame('seasonal', GamePack::CATEGORY_SEASONAL);
    }

    public function test_get_price_returns_monthly_yearly_one_time(): void
    {
        $pack = GamePack::create([
            'name' => 'Pro',
            'slug' => 'pro-' . uniqid(),
            'price_monthly' => 39.99,
            'price_yearly' => 399.99,
            'price_one_time' => 99.99,
        ]);
        $this->assertSame(39.99, $pack->getPrice('monthly'));
        $this->assertSame(399.99, $pack->getPrice('yearly'));
        $this->assertSame(99.99, $pack->getPrice('one_time'));
        $this->assertSame(39.99, $pack->getPrice('unknown'));
    }

    public function test_get_game_count_returns_zero_when_no_games(): void
    {
        $pack = GamePack::create(['name' => 'Empty', 'slug' => 'empty-' . uniqid()]);
        $this->assertSame(0, $pack->getGameCount());
    }

    public function test_scope_active_filters_is_active(): void
    {
        $active = GamePack::create(['name' => 'A', 'slug' => 'a-' . uniqid(), 'is_active' => true]);
        $inactive = GamePack::create(['name' => 'B', 'slug' => 'b-' . uniqid(), 'is_active' => false]);
        $ids = GamePack::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_scope_subscription_filters_type(): void
    {
        $sub = GamePack::create(['name' => 'Sub', 'slug' => 'sub-' . uniqid(), 'type' => GamePack::TYPE_SUBSCRIPTION]);
        $oneTime = GamePack::create(['name' => 'One', 'slug' => 'one-' . uniqid(), 'type' => GamePack::TYPE_ONE_TIME]);
        $ids = GamePack::subscription()->pluck('id')->all();
        $this->assertContains($sub->id, $ids);
        $this->assertNotContains($oneTime->id, $ids);
    }
}
