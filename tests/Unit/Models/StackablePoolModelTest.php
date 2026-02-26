<?php

namespace Tests\Unit\Models;

use App\Models\StackablePool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StackablePoolModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_active_filters_by_is_active_and_date_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-22 12:00:00'));
        $user = User::factory()->create();
        $active = StackablePool::create([
            'name' => 'Active Pool',
            'code' => 'POOL1',
            'created_by' => $user->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $inactive = StackablePool::create([
            'name' => 'Inactive Pool',
            'code' => 'POOL2',
            'created_by' => $user->id,
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $expired = StackablePool::create([
            'name' => 'Expired Pool',
            'code' => 'POOL3',
            'created_by' => $user->id,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        $ids = StackablePool::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($expired->id, $ids);
    }

    public function test_creator_relationship(): void
    {
        $user = User::factory()->create();
        $pool = StackablePool::create([
            'name' => 'Test Pool',
            'code' => 'POOL1',
            'created_by' => $user->id,
            'is_active' => true,
        ]);
        $this->assertTrue($pool->creator->is($user));
    }
}
