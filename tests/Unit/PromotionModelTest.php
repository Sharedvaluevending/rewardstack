<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_currently_valid_scope_includes_active_and_date_range(): void
    {
        $business = Business::factory()->create();

        $active = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Active Promo',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $inactive = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Inactive Promo',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $expired = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Expired Promo',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($active->isCurrentlyValid());
        $this->assertFalse($inactive->isCurrentlyValid());
        $this->assertFalse($expired->isCurrentlyValid());
    }

    public function test_needs_calculator_returns_expected_for_discount_types(): void
    {
        $business = Business::factory()->create();

        $fixed = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Fixed',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $this->assertFalse($fixed->needsCalculator());

        $bogo = Promotion::create([
            'business_id' => $business->id,
            'name' => 'BOGO',
            'discount_type' => Promotion::TYPE_BOGO,
            'discount_value' => 0,
            'is_active' => true,
        ]);
        $this->assertTrue($bogo->needsCalculator());

        $punch = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Punch',
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'discount_value' => 0,
            'is_active' => true,
        ]);
        $this->assertTrue($punch->needsCalculator());

        $pctNoPrice = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Pct',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'original_price' => null,
            'is_active' => true,
        ]);
        $this->assertTrue($pctNoPrice->needsCalculator());

        $pctWithPrice = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Pct fixed',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'original_price' => 20.00,
            'is_active' => true,
        ]);
        $this->assertFalse($pctWithPrice->needsCalculator());
    }

    public function test_get_display_description_returns_expected_strings(): void
    {
        $business = Business::factory()->create();

        $pct = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Pct',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'is_active' => true,
        ]);
        $this->assertStringContainsString('% Off', $pct->getDisplayDescription());
        $this->assertStringContainsString('15', $pct->getDisplayDescription());

        $fixed = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Fixed',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $this->assertStringContainsString('Off', $fixed->getDisplayDescription());
        $this->assertStringContainsString('5', $fixed->getDisplayDescription());

        $bogo = Promotion::create([
            'business_id' => $business->id,
            'name' => 'BOGO',
            'discount_type' => Promotion::TYPE_BOGO,
            'discount_value' => 0,
            'is_active' => true,
        ]);
        $this->assertSame('Buy One Get One Free', $bogo->getDisplayDescription());
    }

    public function test_get_final_price_returns_null_when_not_applicable(): void
    {
        $business = Business::factory()->create();

        $fixed = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Fixed',
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $this->assertNull($fixed->getFinalPrice());
    }

    public function test_get_final_price_returns_calculated_price_for_percentage(): void
    {
        $business = Business::factory()->create();

        $pct = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Pct',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'original_price' => 100.00,
            'is_active' => true,
        ]);
        $this->assertSame(80.0, $pct->getFinalPrice());
    }

    public function test_punch_icon_options_returns_expected_keys(): void
    {
        $options = Promotion::punchIconOptions();
        $this->assertArrayHasKey('☕', $options);
        $this->assertArrayHasKey('⭐', $options);
        $this->assertSame('Coffee', $options['☕']);
    }

    public function test_get_punch_icon_returns_default_when_null(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Punch',
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'discount_value' => 0,
            'is_active' => true,
            'punch_icon' => null,
        ]);
        $this->assertSame('⭐', $promo->getPunchIcon());
    }

    public function test_get_punch_icon_returns_icon_when_set(): void
    {
        $business = Business::factory()->create();
        $promo = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Punch',
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'discount_value' => 0,
            'is_active' => true,
            'punch_icon' => '🍕',
        ]);
        $this->assertSame('🍕', $promo->getPunchIcon());
    }
}

