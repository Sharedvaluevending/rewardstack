<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRedeemProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_redeem_customer_token_once(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-1001',
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/employee/redeem/' . $token->code, [
                'original_amount' => 20,
                'customer_identifier' => 'CUST-1',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $token->refresh();
        $this->assertNotNull($token->redeemed_at);

        $this->assertDatabaseHas('redemptions', [
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
        ]);

        // Second attempt should not create another redemption (idempotency/safety)
        $this->actingAs($employeeUser)
            ->postJson('/employee/redeem/' . $token->code, [
                'original_amount' => 20,
                'customer_identifier' => 'CUST-1',
            ])
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'This customer promo has already been redeemed. Please scan again for a new code.',
            ]);

        $this->assertSame(1, Redemption::where('user_promo_token_id', $token->id)->count());
    }

    public function test_employee_redeem_accepts_code_with_whitespace(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => true,
        ]);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);
        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promo->id,
            'is_active' => true,
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promo->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-2001',
        ]);

        // Code with leading space (URL-encoded as %20)
        $this->actingAs($employeeUser)
            ->postJson('/employee/redeem/%20UP-TEST-2001', [
                'original_amount' => 20,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $token->refresh();
        $this->assertNotNull($token->redeemed_at);
    }

    public function test_employee_without_redeem_permission_is_blocked(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $business->owner->update(['role' => 'business']);

        $employeeUser = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'business_id' => $business->id,
            'role' => 'employee',
            'is_active' => true,
            'can_redeem' => false,
        ]);

        $this->actingAs($employeeUser)
            ->postJson('/employee/redeem/UP-TEST-9999', [
                'original_amount' => 20,
                'customer_identifier' => 'CUST-1',
            ])
            ->assertStatus(403);
    }
}

