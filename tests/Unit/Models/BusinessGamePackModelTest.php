<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessGamePack;
use App\Models\GamePack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessGamePackModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('active', BusinessGamePack::STATUS_ACTIVE);
        $this->assertSame('cancelled', BusinessGamePack::STATUS_CANCELLED);
        $this->assertSame('expired', BusinessGamePack::STATUS_EXPIRED);
        $this->assertSame('trial', BusinessGamePack::STATUS_TRIAL);
    }

    public function test_scope_active_includes_active_and_trial(): void
    {
        $business = Business::factory()->create();
        $pack = GamePack::create(['name' => 'Pack A', 'slug' => 'pack-a-' . uniqid()]);
        $active = BusinessGamePack::create([
            'business_id' => $business->id,
            'game_pack_id' => $pack->id,
            'status' => BusinessGamePack::STATUS_ACTIVE,
        ]);
        $pack2 = GamePack::create(['name' => 'Pack B', 'slug' => 'pack-b-' . uniqid()]);
        $pack3 = GamePack::create(['name' => 'Pack C', 'slug' => 'pack-c-' . uniqid()]);
        $trial = BusinessGamePack::create([
            'business_id' => $business->id,
            'game_pack_id' => $pack2->id,
            'status' => BusinessGamePack::STATUS_TRIAL,
        ]);
        $cancelled = BusinessGamePack::create([
            'business_id' => $business->id,
            'game_pack_id' => $pack3->id,
            'status' => BusinessGamePack::STATUS_CANCELLED,
        ]);

        $ids = BusinessGamePack::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($trial->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_is_active_returns_true_when_active_and_no_end_date(): void
    {
        $business = Business::factory()->create();
        $pack = GamePack::create(['name' => 'Pack', 'slug' => 'pack-' . uniqid()]);
        $bgp = BusinessGamePack::create([
            'business_id' => $business->id,
            'game_pack_id' => $pack->id,
            'status' => BusinessGamePack::STATUS_ACTIVE,
            'ends_at' => null,
        ]);
        $this->assertTrue($bgp->isActive());
    }

    public function test_is_active_returns_false_when_status_cancelled(): void
    {
        $business = Business::factory()->create();
        $pack = GamePack::create(['name' => 'Pack', 'slug' => 'pack-' . uniqid()]);
        $bgp = BusinessGamePack::create([
            'business_id' => $business->id,
            'game_pack_id' => $pack->id,
            'status' => BusinessGamePack::STATUS_CANCELLED,
        ]);
        $this->assertFalse($bgp->isActive());
    }
}
