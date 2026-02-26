<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromotionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_forBusiness_scope_returns_related_promos(): void
    {
        $b1 = Business::factory()->create();
        $b2 = Business::factory()->create();
        $b3 = Business::factory()->create();

        $p1 = Promotion::create([
            'business_id' => $b1->id,
            'name' => 'P1',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $p2 = Promotion::create([
            'business_id' => $b2->id,
            'name' => 'P2',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cp1 = CrossPromotion::create([
            'code' => 'CP1',
            'name' => 'CP1',
            'business_1_id' => $b1->id,
            'business_2_id' => $b2->id,
            'requested_by_business_id' => $b1->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => $p2->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);

        $cp2 = CrossPromotion::create([
            'code' => 'CP2',
            'name' => 'CP2',
            'business_1_id' => $b3->id,
            'business_2_id' => $b1->id,
            'requested_by_business_id' => $b3->id,
            'promotion_1_id' => $p1->id,
            'promotion_2_id' => null,
            'status' => CrossPromotion::STATUS_PENDING,
            'is_active' => false,
        ]);

        $results = CrossPromotion::forBusiness($b1->id)->pluck('id')->all();

        $this->assertContains($cp1->id, $results);
        $this->assertContains($cp2->id, $results);
    }
}

