<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\BusinessCustomerSubscription;
use App\Models\CrmSegment;
use App\Models\User;
use App\Services\CrmAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSegmentTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'subscription_tier' => 'pro',
            'is_testing_account' => true,
        ]);

        $this->service = app(CrmAudienceService::class);
    }

    /** @test */
    public function segment_filters_by_last_seen_days()
    {
        // Segment: Seen in last 30 days
        $segment = CrmSegment::create([
            'business_id' => $this->business->id,
            'name' => 'Recent Visitors',
            'definition' => ['filters' => ['last_seen_days' => 30]],
            'is_active' => true,
        ]);

        // User A: Seen 10 days ago (Should Match)
        $userA = $this->createSubscribedUser('userA@example.com');
        $this->createCustomerStats($userA, ['last_seen_at' => now()->subDays(10)]);

        // User B: Seen 40 days ago (Should NOT Match)
        $userB = $this->createSubscribedUser('userB@example.com');
        $this->createCustomerStats($userB, ['last_seen_at' => now()->subDays(40)]);

        // Apply Segment
        $query = $this->service->subscribedUsersQuery($this->business);
        $query = $this->service->applySegment($this->business, $query, $segment);
        $results = $query->get();

        $this->assertTrue($results->contains('id', $userA->id));
        $this->assertFalse($results->contains('id', $userB->id));
    }

    /** @test */
    public function segment_filters_by_min_scans()
    {
        // Segment: At least 5 scans
        $segment = CrmSegment::create([
            'business_id' => $this->business->id,
            'name' => 'Frequent Scanners',
            'definition' => ['filters' => ['min_scans' => 5]],
            'is_active' => true,
        ]);

        // User A: 10 scans (Should Match)
        $userA = $this->createSubscribedUser('userA@example.com');
        $this->createCustomerStats($userA, ['scans_count' => 10]);

        // User B: 2 scans (Should NOT Match)
        $userB = $this->createSubscribedUser('userB@example.com');
        $this->createCustomerStats($userB, ['scans_count' => 2]);

        // Apply Segment
        $query = $this->service->subscribedUsersQuery($this->business);
        $query = $this->service->applySegment($this->business, $query, $segment);
        $results = $query->get();

        $this->assertTrue($results->contains('id', $userA->id));
        $this->assertFalse($results->contains('id', $userB->id));
    }

    /** @test */
    public function segment_filters_by_min_redemptions()
    {
        // Segment: At least 3 redemptions
        $segment = CrmSegment::create([
            'business_id' => $this->business->id,
            'name' => 'Redeemers',
            'definition' => ['filters' => ['min_redemptions' => 3]],
            'is_active' => true,
        ]);

        // User A: 5 redemptions (Should Match)
        $userA = $this->createSubscribedUser('userA@example.com');
        $this->createCustomerStats($userA, ['redemptions_count' => 5]);

        // User B: 0 redemptions (Should NOT Match)
        $userB = $this->createSubscribedUser('userB@example.com');
        $this->createCustomerStats($userB, ['redemptions_count' => 0]);

        // Apply Segment
        $query = $this->service->subscribedUsersQuery($this->business);
        $query = $this->service->applySegment($this->business, $query, $segment);
        $results = $query->get();

        $this->assertTrue($results->contains('id', $userA->id));
        $this->assertFalse($results->contains('id', $userB->id));
    }

    /** @test */
    public function segment_filters_combine_multiple_conditions()
    {
        // Segment: Recent AND High Value (Seen in last 30 days AND > 5 scans)
        $segment = CrmSegment::create([
            'business_id' => $this->business->id,
            'name' => 'Active VIPs',
            'definition' => ['filters' => [
                'last_seen_days' => 30,
                'min_scans' => 5
            ]],
            'is_active' => true,
        ]);

        // User A: Recent (10 days) + High Scans (10) -> Match
        $userA = $this->createSubscribedUser('userA@example.com');
        $this->createCustomerStats($userA, [
            'last_seen_at' => now()->subDays(10),
            'scans_count' => 10
        ]);

        // User B: Recent (10 days) + Low Scans (2) -> No Match
        $userB = $this->createSubscribedUser('userB@example.com');
        $this->createCustomerStats($userB, [
            'last_seen_at' => now()->subDays(10),
            'scans_count' => 2
        ]);

        // User C: Old (40 days) + High Scans (10) -> No Match
        $userC = $this->createSubscribedUser('userC@example.com');
        $this->createCustomerStats($userC, [
            'last_seen_at' => now()->subDays(40),
            'scans_count' => 10
        ]);

        // Apply Segment
        $query = $this->service->subscribedUsersQuery($this->business);
        $query = $this->service->applySegment($this->business, $query, $segment);
        $results = $query->get();

        $this->assertTrue($results->contains('id', $userA->id));
        $this->assertFalse($results->contains('id', $userB->id));
        $this->assertFalse($results->contains('id', $userC->id));
    }

    protected function createSubscribedUser(string $email)
    {
        $user = User::factory()->create(['email' => $email, 'role' => 'customer']);
        BusinessCustomerSubscription::create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'subscribed_at' => now(),
        ]);
        return $user;
    }

    protected function createCustomerStats(User $user, array $stats)
    {
        BusinessCustomer::create(array_merge([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'last_seen_at' => now(),
            'scans_count' => 0,
            'redemptions_count' => 0,
            'saved_count' => 0,
            'lifetime_savings' => 0,
        ], $stats));
    }
}
