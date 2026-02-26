<?php

namespace Tests\Unit\Models;

use App\Models\AIInsight;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIInsightModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_relationship(): void
    {
        $business = Business::factory()->create();
        $insight = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Test',
            'description' => 'Desc',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $this->assertTrue($insight->business->is($business));
    }

    public function test_scope_unread_filters_unread_only(): void
    {
        $business = Business::factory()->create();
        $read = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Read',
            'description' => 'Read insight',
            'is_read' => true,
            'is_dismissed' => false,
        ]);
        $unread = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Unread',
            'description' => 'Unread insight',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $ids = AIInsight::unread()->pluck('id')->all();
        $this->assertNotContains($read->id, $ids);
        $this->assertContains($unread->id, $ids);
    }

    public function test_scope_active_excludes_dismissed_and_expired(): void
    {
        $business = Business::factory()->create();
        $active = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Active',
            'description' => 'Active insight',
            'is_read' => false,
            'is_dismissed' => false,
            'expires_at' => null,
        ]);
        $dismissed = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Dismissed',
            'description' => 'Dismissed insight',
            'is_read' => false,
            'is_dismissed' => true,
        ]);
        $expired = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Expired',
            'description' => 'Expired insight',
            'is_read' => false,
            'is_dismissed' => false,
            'expires_at' => now()->subDay(),
        ]);

        $ids = AIInsight::active()->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($dismissed->id, $ids);
        $this->assertNotContains($expired->id, $ids);
    }

    public function test_mark_as_read_sets_is_read_true(): void
    {
        $business = Business::factory()->create();
        $insight = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Test',
            'description' => 'Test insight',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $insight->markAsRead();

        $insight->refresh();
        $this->assertTrue($insight->is_read);
    }

    public function test_dismiss_sets_is_dismissed_true(): void
    {
        $business = Business::factory()->create();
        $insight = AIInsight::create([
            'business_id' => $business->id,
            'type' => 'tip',
            'category' => 'engagement',
            'title' => 'Test',
            'description' => 'Test insight',
            'is_read' => false,
            'is_dismissed' => false,
        ]);

        $insight->dismiss();

        $insight->refresh();
        $this->assertTrue($insight->is_dismissed);
    }
}
