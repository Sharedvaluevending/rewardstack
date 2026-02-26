<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossPromoRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_promo_can_have_custom_rules()
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        
        $crossPromo = CrossPromotion::create([
            'code' => 'TEST123',
            'name' => 'Test Cross Promo',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => 'split',
            'chain_mode' => 'open',
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'rules_status' => CrossPromotion::RULES_AGREED,
            'cross_promo_rules' => [
                'valid_days' => ['monday', 'tuesday', 'wednesday'],
                'valid_hours' => ['start' => '09:00', 'end' => '17:00'],
                'max_redemptions_per_user' => 2,
            ],
        ]);
        
        $this->assertNotNull($crossPromo->cross_promo_rules);
        $this->assertEquals(CrossPromotion::RULES_AGREED, $crossPromo->rules_status);
        $this->assertEquals(['monday', 'tuesday', 'wednesday'], $crossPromo->cross_promo_rules['valid_days']);
    }
    
    public function test_cross_promo_expiration_logic()
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();
        
        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        
        // Active cross-promo
        $activePromo = CrossPromotion::create([
            'code' => 'ACTIVE',
            'name' => 'Active',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => 'split',
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'expires_at' => now()->addDays(30),
        ]);
        
        // Expired cross-promo
        $expiredPromo = CrossPromotion::create([
            'code' => 'EXPIRED',
            'name' => 'Expired',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => 'split',
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
            'expires_at' => now()->subDays(1),
        ]);
        
        $this->assertTrue($activePromo->isValid());
        $this->assertFalse($expiredPromo->isValid());
    }
}
