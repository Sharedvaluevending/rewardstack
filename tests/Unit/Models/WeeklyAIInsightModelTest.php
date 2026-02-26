<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\WeeklyAIInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyAIInsightModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_relationship(): void
    {
        $business = Business::factory()->create();
        $insight = WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 7,
            'payload' => ['summary' => 'Test'],
            'generated_at' => now(),
        ]);

        $this->assertTrue($insight->business->is($business));
    }

    public function test_payload_is_cast_to_array(): void
    {
        $business = Business::factory()->create();
        $payload = ['summary' => 'Foo', 'quick_insights' => []];
        $insight = WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 7,
            'payload' => $payload,
            'generated_at' => now(),
        ]);

        $insight->refresh();
        $this->assertIsArray($insight->payload);
        $this->assertSame('Foo', $insight->payload['summary']);
    }

    public function test_get_latest_returns_record_when_exists(): void
    {
        $business = Business::factory()->create();
        $insight = WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 7,
            'payload' => ['summary' => 'Test insight'],
            'generated_at' => now(),
        ]);

        $latest = WeeklyAIInsight::getLatest($business->id, 'basic', 7);
        $this->assertNotNull($latest);
        $this->assertSame($insight->id, $latest->id);
        $this->assertSame('Test insight', $latest->payload['summary']);
    }

    public function test_get_latest_returns_null_when_no_match(): void
    {
        $business = Business::factory()->create();
        $this->assertNull(WeeklyAIInsight::getLatest($business->id, 'basic', 7));
    }

    public function test_get_latest_filters_by_business_type_and_period(): void
    {
        $business = Business::factory()->create();
        WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 30,
            'payload' => ['v' => 1],
            'generated_at' => now(),
        ]);
        $basic7 = WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 7,
            'payload' => ['v' => 2],
            'generated_at' => now(),
        ]);

        $this->assertNull(WeeklyAIInsight::getLatest($business->id, 'basic', 90));
        $latest = WeeklyAIInsight::getLatest($business->id, 'basic', 7);
        $this->assertSame($basic7->id, $latest->id);
    }

    public function test_store_creates_new_record(): void
    {
        $business = Business::factory()->create();
        $payload = ['summary' => 'New insight'];

        $insight = WeeklyAIInsight::store($business->id, 'basic', 7, $payload);

        $this->assertDatabaseHas('weekly_ai_insights', [
            'business_id' => $business->id,
            'type' => 'basic',
            'period' => 7,
        ]);
        $this->assertSame($payload, $insight->payload);
    }

    public function test_store_updates_existing_record(): void
    {
        $business = Business::factory()->create();
        $original = WeeklyAIInsight::create([
            'business_id' => $business->id,
            'type' => 'advanced',
            'period' => 7,
            'payload' => ['old' => true],
            'generated_at' => now()->subDay(),
        ]);

        $updated = WeeklyAIInsight::store($business->id, 'advanced', 7, ['new' => true]);

        $this->assertSame($original->id, $updated->id);
        $this->assertSame(['new' => true], $updated->payload);
        $this->assertSame(1, WeeklyAIInsight::where('business_id', $business->id)->where('type', 'advanced')->count());
    }
}
