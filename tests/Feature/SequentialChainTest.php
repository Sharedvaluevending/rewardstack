<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CrossPromotion;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialChainTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequential_chain_unlocks_after_primary_redeemed()
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();

        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        
        $crossPromo = CrossPromotion::create([
            'code' => 'CHAIN123',
            'name' => 'Sequential Chain',
            'business_1_id' => $business1->id,
            'business_2_id' => $business2->id,
            'promotion_1_id' => $promo1->id,
            'promotion_2_id' => $promo2->id,
            'display_mode' => 'split',
            'chain_mode' => CrossPromotion::CHAIN_SEQUENTIAL,
            'primary_promotion_id' => $promo1->id,
            'status' => CrossPromotion::STATUS_ACCEPTED,
            'is_active' => true,
        ]);
        
        $user = User::factory()->create();
        
        // Create a QR code for the cross-promo
        $qrCode = QRCode::create([
            'business_id' => $business1->id,
            'type' => 'cross_promo',
            'cross_promotion_id' => $crossPromo->id,
            'name' => 'Test QR',
            'code' => 'TESTQR123',
        ]);
        
        // Initially, secondary should be locked
        $this->assertTrue($crossPromo->isPromotionLocked($promo2->id, $user->id));
        $this->assertFalse($crossPromo->isPromotionLocked($promo1->id, $user->id));
        
        // Create a redeemed token for primary
        UserPromoToken::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promo1->id,
            'business_id' => $business1->id,
            'code' => 'TEST123',
            'redeemed_at' => now(),
        ]);
        
        // After primary is redeemed, secondary should unlock
        $crossPromo->refresh();
        $this->assertFalse($crossPromo->isPromotionLocked($promo2->id, $user->id));
    }
    
    public function test_expired_cross_promo_is_not_valid()
    {
        $business1 = Business::factory()->create();
        $business2 = Business::factory()->create();
        
        $promo1 = Promotion::factory()->create(['business_id' => $business1->id]);
        $promo2 = Promotion::factory()->create(['business_id' => $business2->id]);
        
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
        
        $this->assertFalse($expiredPromo->isValid());
        
        // Auto-deactivate should work
        $expiredPromo->checkAndDeactivateIfExpired();
        $expiredPromo->refresh();
        
        $this->assertFalse($expiredPromo->is_active);
    }
}
