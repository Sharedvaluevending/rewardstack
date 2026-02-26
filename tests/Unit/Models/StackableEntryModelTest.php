<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\StackableEntry;
use App\Models\StackablePool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StackableEntryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_active_filters_by_is_active_and_is_approved(): void
    {
        $user = User::factory()->create();
        $pool = StackablePool::create([
            'name' => 'Pool',
            'code' => 'POOL' . uniqid(),
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);

        $active = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
            'is_approved' => true,
        ]);
        $inactive = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => Promotion::factory()->create(['business_id' => $business->id])->id,
            'is_active' => false,
            'is_approved' => true,
        ]);
        $unapproved = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => Promotion::factory()->create(['business_id' => $business->id])->id,
            'is_active' => true,
            'is_approved' => false,
        ]);

        $ids = StackableEntry::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($unapproved->id, $ids);
    }

    public function test_scope_for_business_filters_by_business_id(): void
    {
        $user = User::factory()->create();
        $pool = StackablePool::create([
            'name' => 'Pool',
            'code' => 'POOL' . uniqid(),
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $e1 = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $b1->id,
            'promotion_id' => Promotion::factory()->create(['business_id' => $b1->id])->id,
        ]);
        $e2 = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $b2->id,
            'promotion_id' => Promotion::factory()->create(['business_id' => $b2->id])->id,
        ]);

        $ids = StackableEntry::forBusiness($b1->id)->pluck('id')->all();
        $this->assertContains($e1->id, $ids);
        $this->assertNotContains($e2->id, $ids);
    }

    public function test_approve_sets_is_approved_and_approved_at(): void
    {
        $user = User::factory()->create();
        $pool = StackablePool::create([
            'name' => 'Pool',
            'code' => 'POOL' . uniqid(),
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $entry = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_approved' => false,
        ]);

        $entry->approve();

        $entry->refresh();
        $this->assertTrue($entry->is_approved);
        $this->assertNotNull($entry->approved_at);
    }

    public function test_relationships_exist(): void
    {
        $user = User::factory()->create();
        $pool = StackablePool::create([
            'name' => 'Pool',
            'code' => 'POOL' . uniqid(),
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $business = Business::factory()->create();
        $promo = Promotion::factory()->create(['business_id' => $business->id]);
        $entry = StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
        ]);

        $this->assertTrue($entry->pool->is($pool));
        $this->assertTrue($entry->business->is($business));
        $this->assertTrue($entry->promotion->is($promo));
    }
}
