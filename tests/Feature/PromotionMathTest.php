<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionMathTest extends TestCase
{
    use RefreshDatabase;

    protected $business;
    protected $customer;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->business = Business::factory()->create();
        $this->customer = User::factory()->create(['role' => 'customer']);
        
        // Create employee
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $this->employee = Employee::create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'role' => 'employee',
            'can_redeem' => true,
            'is_active' => true,
        ]);
        $this->employee->load('user');
    }

    /** @test */
    public function percentage_off_calculation_is_correct()
    {
        // 20% Off
        $promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $promotion->id,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-MATH-PERC',
        ]);

        // Sale amount: $100.00
        // Expected Discount: $20.00 (20%)
        // Expected Final Cost: $80.00
        
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 100.00,
            ]);
            
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('redemptions', [
            'user_promo_token_id' => $token->id,
            'original_amount' => 100.00, 
            'discount_amount' => 20.00,
            'final_amount' => 80.00,
        ]);
    }

    /** @test */
    public function fixed_amount_off_calculation_is_correct()
    {
        // $10.00 Off
        $promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => Promotion::TYPE_FIXED_AMOUNT,
            'discount_value' => 10, // $10.00
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $promotion->id,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-MATH-FIXED',
        ]);

        // Sale amount: $50.00
        // Expected Discount: $10.00
        // Expected Final Cost: $40.00
        
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 50.00,
            ]);
            
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('redemptions', [
            'user_promo_token_id' => $token->id,
            'original_amount' => 50.00, 
            'discount_amount' => 10.00,  
            'final_amount' => 40.00,     
        ]);
    }

    /** @test */
    public function bogo_calculation_is_correct()
    {
        // Buy One Get One (BOGO)
        $promotion = Promotion::factory()->create([
            'business_id' => $this->business->id,
            'discount_type' => Promotion::TYPE_BOGO,
        ]);

        $qrCode = QRCode::factory()->create([
            'business_id' => $this->business->id,
            'promotion_id' => $promotion->id,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $this->customer->id,
            'qr_code_id' => $qrCode->id,
            'promotion_id' => $promotion->id,
            'business_id' => $this->business->id,
            'code' => 'UP-TEST-MATH-BOGO',
        ]);

        // Scenario: Two items, each $10. 
        // Input should be the PAID amount ($10).
        // System assumes Discount = Paid Amount ($10).
        // Original Total becomes $20.
        // Final Paid becomes $10.
        
        $response = $this->actingAs($this->employee->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post("/employee/redeem/{$token->code}", [
                'original_amount' => 10.00,
            ]);
            
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('redemptions', [
            'user_promo_token_id' => $token->id,
            'original_amount' => 20.00, // Total Value ($10 paid + $10 free)
            'discount_amount' => 10.00, // Savings ($10 free)
            'final_amount' => 10.00,    // Paid ($10)
        ]);
    }
}
