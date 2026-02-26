<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\StackableEntry;
use App\Models\StackablePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicStackablePoolScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_stackable_pool_scan_renders_only_active_approved_entries_and_sorts_featured_first(): void
    {
        $pool = StackablePool::create([
            'name' => 'Downtown Pool',
            'code' => 'POOL0001',
            'description' => 'Test pool',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'city' => 'New York',
        ]);

        $hostBusiness = Business::factory()->create([
            'subscription_tier' => 'starter',
        ]);

        $qr = QRCode::create([
            'business_id' => $hostBusiness->id,
            'code' => 'STKP1234',
            'name' => 'Stackable Pool QR',
            'type' => 'stackable',
            'stackable_pool_id' => $pool->id,
            'is_active' => true,
        ]);

        $b1 = Business::factory()->create(['name' => 'Biz A', 'is_active' => true]);
        $b2 = Business::factory()->create(['name' => 'Biz B', 'is_active' => true]);
        $b3 = Business::factory()->create(['name' => 'Biz C', 'is_active' => true]);

        $p1 = Promotion::factory()->create(['business_id' => $b1->id, 'is_active' => true]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id, 'is_active' => true]);
        $p3 = Promotion::factory()->create(['business_id' => $b3->id, 'is_active' => true]);

        QRCode::factory()->create([
            'business_id' => $b1->id,
            'promotion_id' => $p1->id,
            'type' => 'stackable',
            'is_active' => true,
        ]);
        QRCode::factory()->create([
            'business_id' => $b2->id,
            'promotion_id' => $p2->id,
            'type' => 'stackable',
            'is_active' => true,
        ]);

        // Should appear (featured first)
        StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $b1->id,
            'promotion_id' => $p1->id,
            'sort_order' => 50,
            'is_featured' => true,
            'is_active' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        // Should appear (non-featured, lower sort_order)
        StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $b2->id,
            'promotion_id' => $p2->id,
            'sort_order' => 1,
            'is_featured' => false,
            'is_active' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        // Should NOT appear (not approved)
        StackableEntry::create([
            'stackable_pool_id' => $pool->id,
            'business_id' => $b3->id,
            'promotion_id' => $p3->id,
            'sort_order' => 0,
            'is_featured' => true,
            'is_active' => true,
            'is_approved' => false,
            'approved_at' => null,
        ]);

        $this->get('/s/' . $qr->code)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) =>
                $page->component('Public/StackablePool')
                    ->where('qrCode.code', $qr->code)
                    ->has('entries', 2)
                    ->where('entries.0.is_featured', true)
                    ->where('entries.0.business.name', 'Biz A')
                    ->where('entries.1.is_featured', false)
                    ->where('entries.1.business.name', 'Biz B')
            );
    }
}

