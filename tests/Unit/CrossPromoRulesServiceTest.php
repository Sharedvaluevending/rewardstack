<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Promotion;
use App\Services\CrossPromoRulesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoRulesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_rules_returns_empty_for_valid_rules(): void
    {
        $service = new CrossPromoRulesService();
        $errors = $service->validateRules([
            'valid_days' => ['monday', 'tuesday'],
            'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
            'max_redemptions_per_user' => 5,
            'max_per_day' => 2,
        ]);
        $this->assertSame([], $errors);
    }

    public function test_validate_rules_returns_error_for_invalid_day(): void
    {
        $service = new CrossPromoRulesService();
        $errors = $service->validateRules([
            'valid_days' => ['monday', 'invalidday'],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid day', $errors[0]);
    }

    public function test_validate_rules_returns_error_for_invalid_time_format(): void
    {
        $service = new CrossPromoRulesService();
        $errors = $service->validateRules([
            'valid_hours' => ['start' => '9:00', 'end' => '17:00'],
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_validate_rules_returns_error_for_negative_max_redemptions(): void
    {
        $service = new CrossPromoRulesService();
        $errors = $service->validateRules([
            'max_redemptions_per_user' => -1,
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('max_redemptions_per_user', $errors[0]);
    }

    public function test_validate_rules_accepts_valid_hours_hh_mm(): void
    {
        $service = new CrossPromoRulesService();
        $errors = $service->validateRules([
            'valid_hours' => ['start' => '09:00', 'end' => '23:59'],
        ]);
        $this->assertSame([], $errors);
    }

    public function test_merge_rules_intersects_valid_days(): void
    {
        $business = Business::factory()->create();
        $promo1 = Promotion::factory()->create(['business_id' => $business->id, 'rules' => ['valid_days' => ['monday', 'tuesday']]]);
        $promo2 = Promotion::factory()->create(['business_id' => $business->id, 'rules' => ['valid_days' => ['monday', 'wednesday']]]);
        $service = new CrossPromoRulesService();
        $merged = $service->mergeRules($promo1, $promo2);
        $this->assertContains('monday', $merged['valid_days']);
        $this->assertCount(1, $merged['valid_days']);
    }

    public function test_merge_rules_uses_more_restrictive_limit(): void
    {
        $business = Business::factory()->create();
        $promo1 = Promotion::factory()->create(['business_id' => $business->id, 'rules' => ['max_redemptions_per_user' => 5]]);
        $promo2 = Promotion::factory()->create(['business_id' => $business->id, 'rules' => ['max_redemptions_per_user' => 3]]);
        $service = new CrossPromoRulesService();
        $merged = $service->mergeRules($promo1, $promo2);
        $this->assertSame(3, $merged['max_redemptions_per_user']);
    }

    public function test_format_rules_for_display_ucfirst_days_and_unlimited(): void
    {
        $service = new CrossPromoRulesService();
        $formatted = $service->formatRulesForDisplay([
            'valid_days' => ['monday', 'tuesday'],
            'max_redemptions_per_user' => 0,
            'max_per_day' => 2,
        ]);
        $this->assertContains('Monday', $formatted['valid_days']);
        $this->assertSame('Unlimited', $formatted['max_redemptions_per_user']);
        $this->assertSame(2, $formatted['max_per_day']);
    }
}
