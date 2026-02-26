<?php

namespace Tests\Unit\Models;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrossPromotionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_valid_returns_false_when_inactive(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $cp = CrossPromotion::create([
            'code' => 'CP' . uniqid(),
            'name' => 'Test',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);
        $this->assertFalse($cp->isValid());
    }

    public function test_is_valid_returns_false_when_not_accepted(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $cp = CrossPromotion::create([
            'code' => 'CP' . uniqid(),
            'name' => 'Test',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);
        $this->assertFalse($cp->isValid());
    }

    public function test_is_valid_returns_false_when_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-22 12:00:00'));
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $cp = CrossPromotion::create([
            'code' => 'CP' . uniqid(),
            'name' => 'Test',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
        $this->assertFalse($cp->isValid());
    }

    public function test_is_valid_returns_true_when_active_accepted_and_in_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-22 12:00:00'));
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $cp = CrossPromotion::create([
            'code' => 'CP' . uniqid(),
            'name' => 'Test',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);
        $this->assertTrue($cp->isValid());
    }

    public function test_has_reached_usage_limit_returns_false_when_no_limit_set(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $cp = CrossPromotion::create([
            'code' => 'CP' . uniqid(),
            'name' => 'Test',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'usage_limit' => null,
        ]);
        $this->assertFalse($cp->hasReachedUsageLimit());
    }

    public function test_display_modes_returns_expected_keys(): void
    {
        $modes = CrossPromotion::displayModes();
        $this->assertArrayHasKey(CrossPromotion::DISPLAY_SPLIT, $modes);
        $this->assertArrayHasKey(CrossPromotion::DISPLAY_ALTERNATING, $modes);
        $this->assertArrayHasKey(CrossPromotion::DISPLAY_RANDOM, $modes);
        $this->assertStringContainsString('Split', $modes[CrossPromotion::DISPLAY_SPLIT]);
    }

    public function test_chain_modes_returns_expected_keys(): void
    {
        $modes = CrossPromotion::chainModes();
        $this->assertArrayHasKey(CrossPromotion::CHAIN_OPEN, $modes);
        $this->assertArrayHasKey(CrossPromotion::CHAIN_SEQUENTIAL, $modes);
    }

    public function test_rules_status_constants_are_defined(): void
    {
        $this->assertSame('use_promotion_rules', CrossPromotion::RULES_USE_PROMOTION_RULES);
        $this->assertSame('pending_agreement', CrossPromotion::RULES_PENDING_AGREEMENT);
        $this->assertSame('agreed', CrossPromotion::RULES_AGREED);
        $this->assertSame('overridden', CrossPromotion::RULES_OVERRIDDEN);
    }

    public function test_scope_active_filters_accepted_and_not_expired(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $p1 = Promotion::factory()->create(['business_id' => $b1->id]);
        $p2 = Promotion::factory()->create(['business_id' => $b2->id]);
        $active = CrossPromotion::create([
            'code' => 'A' . uniqid(),
            'name' => 'Active',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'expires_at' => now()->addDays(7),
        ]);
        $inactive = CrossPromotion::create([
            'code' => 'I' . uniqid(),
            'name' => 'Inactive',
            'requested_by_business_id' => $b1->id,
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_DECLINED,
            'is_active' => false,
        ]);
        $result = CrossPromotion::active()->get();
        $this->assertTrue($result->contains($active));
        $this->assertFalse($result->contains($inactive));
    }
}
