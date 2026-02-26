<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\BusinessPartnership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPartnershipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending', BusinessPartnership::STATUS_PENDING);
        $this->assertSame('accepted', BusinessPartnership::STATUS_ACCEPTED);
        $this->assertSame('declined', BusinessPartnership::STATUS_DECLINED);
        $this->assertSame('cancelled', BusinessPartnership::STATUS_CANCELLED);
    }

    public function test_scope_pending_filters_pending_only(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $pending = BusinessPartnership::create([
            'requester_business_id' => $b1->id,
            'partner_business_id' => $b2->id,
            'status' => BusinessPartnership::STATUS_PENDING,
        ]);
        $accepted = BusinessPartnership::create([
            'requester_business_id' => $b2->id,
            'partner_business_id' => $b1->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $ids = BusinessPartnership::pending()->pluck('id')->all();
        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($accepted->id, $ids);
    }

    public function test_scope_accepted_filters_accepted_only(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $accepted = BusinessPartnership::create([
            'requester_business_id' => $b1->id,
            'partner_business_id' => $b2->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $ids = BusinessPartnership::accepted()->pluck('id')->all();
        $this->assertContains($accepted->id, $ids);
    }

    public function test_scope_for_business_includes_requester_and_partner(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $b3 = Business::factory()->create();
        $p1 = BusinessPartnership::create([
            'requester_business_id' => $b1->id,
            'partner_business_id' => $b2->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);
        $p2 = BusinessPartnership::create([
            'requester_business_id' => $b3->id,
            'partner_business_id' => $b1->id,
            'status' => BusinessPartnership::STATUS_ACCEPTED,
        ]);

        $ids = BusinessPartnership::forBusiness($b1->id)->pluck('id')->all();
        $this->assertContains($p1->id, $ids);
        $this->assertContains($p2->id, $ids);
    }
}
