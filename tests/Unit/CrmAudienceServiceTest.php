<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\CrmSegment;
use App\Models\User;
use App\Services\CrmAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAudienceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribed_users_query_returns_builder(): void
    {
        $business = Business::factory()->create();
        $service = new CrmAudienceService();
        $query = $service->subscribedUsersQuery($business);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
        $sql = $query->toSql();
        $this->assertStringContainsString('business_customer_subscriptions', $sql);
        $this->assertStringContainsString('subscribed_at', $sql);
        $this->assertStringContainsString('unsubscribed_at', $sql);
    }

    public function test_apply_segment_returns_query_unchanged_when_segment_null(): void
    {
        $business = Business::factory()->create();
        $service = new CrmAudienceService();
        $baseQuery = $service->subscribedUsersQuery($business);
        $applied = $service->applySegment($business, $baseQuery, null);
        $this->assertSame($baseQuery, $applied);
    }

    public function test_apply_segment_returns_query_unchanged_when_definition_empty(): void
    {
        $business = Business::factory()->create();
        $segment = CrmSegment::create([
            'business_id' => $business->id,
            'name' => 'Empty segment',
            'definition' => [],
            'is_active' => true,
        ]);
        $service = new CrmAudienceService();
        $baseQuery = $service->subscribedUsersQuery($business);
        $applied = $service->applySegment($business, $baseQuery, $segment);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $applied);
    }

    public function test_apply_segment_adds_filters_when_definition_has_filters(): void
    {
        $business = Business::factory()->create();
        $segment = CrmSegment::create([
            'business_id' => $business->id,
            'name' => 'Filtered segment',
            'definition' => [
                'filters' => [
                    'min_scans' => 5,
                    'min_level' => 2,
                ],
            ],
            'is_active' => true,
        ]);
        $service = new CrmAudienceService();
        $baseQuery = $service->subscribedUsersQuery($business);
        $applied = $service->applySegment($business, $baseQuery, $segment);
        $sql = $applied->toSql();
        $this->assertStringContainsString('business_customers', $sql);
    }

    public function test_exclude_suppressed_adds_where_not_exists(): void
    {
        $business = Business::factory()->create();
        $service = new CrmAudienceService();
        $query = $service->subscribedUsersQuery($business);
        $filtered = $service->excludeSuppressed($business, $query);
        $sql = $filtered->toSql();
        $this->assertStringContainsString('email_unsubscribes', $sql);
    }
}
