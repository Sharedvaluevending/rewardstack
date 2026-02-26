<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\Redemption;
use App\Models\SavedQRCode;
use App\Models\User;
use App\Models\UserPromoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedemptionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_token_redeem_is_idempotent_and_consumes_token_once(): void
    {
        $business = Business::factory()->create();

        $staff = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'can_redeem' => true,
            'can_view_analytics' => false,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $promotion = Promotion::factory()->fixedAmount(5.00)->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-TEST-TOKN',
        ]);

        SavedQRCode::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'saved_at' => now(),
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $first = $this->actingAs($staff)->postJson('/employee/redeem/' . $token->code, [
            'original_amount' => 10,
            'quantity' => 1,
        ]);

        $first->assertStatus(200)->assertJson([
            'success' => true,
        ]);

        $token->refresh();
        $this->assertNotNull($token->redeemed_at);
        $this->assertNotNull($token->redemption_id);

        $this->assertDatabaseMissing('saved_qr_codes', [
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
        ]);

        $second = $this->actingAs($staff)->postJson('/employee/redeem/' . $token->code, [
            'original_amount' => 10,
            'quantity' => 1,
        ]);

        $second->assertStatus(400)->assertJson([
            'success' => false,
        ]);

        $this->assertEquals(1, Redemption::query()->where('user_promo_token_id', $token->id)->count());
    }

    public function test_punch_card_redemption_does_not_consume_token_and_does_not_bind_unique_token_id(): void
    {
        $business = Business::factory()->create();

        $staff = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'can_redeem' => true,
            'can_view_analytics' => false,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $promotion = Promotion::factory()->create([
            'business_id' => $business->id,
            'discount_type' => Promotion::TYPE_PUNCH_CARD,
            'punches_required' => 3,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-PUNCH-CARD',
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $resp = $this->actingAs($staff)->postJson('/employee/redeem/' . $token->code, [
            'original_amount' => 0,
            'quantity' => 1,
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);

        $token->refresh();
        $this->assertNull($token->redeemed_at);
        $this->assertNull($token->redemption_id);

        $this->assertEquals(0, Redemption::query()->where('customer_user_id', $customer->id)->whereNotNull('user_promo_token_id')->count());
        $this->assertDatabaseHas('punch_cards', [
            'promotion_id' => $promotion->id,
            'user_id' => $customer->id,
            'punches' => 1,
        ]);
    }

    public function test_redeem_token_lookup_is_rate_limited(): void
    {
        // Ensure rate limiters use "testing" limits even if APP_ENV is misconfigured.
        $this->app->detectEnvironment(fn () => 'testing');

        $business = Business::factory()->create();

        $staff = User::factory()->create(['role' => 'employee']);
        Employee::create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'can_redeem' => true,
            'can_view_analytics' => false,
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['role' => 'customer']);

        $promotion = Promotion::factory()->fixedAmount(5.00)->create([
            'business_id' => $business->id,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $qr = QRCode::factory()->promotion()->create([
            'business_id' => $business->id,
            'promotion_id' => $promotion->id,
            'is_active' => true,
        ]);

        $token = UserPromoToken::create([
            'user_id' => $customer->id,
            'qr_code_id' => $qr->id,
            'promotion_id' => $promotion->id,
            'business_id' => $business->id,
            'code' => 'UP-LOOK-UP01',
        ]);

        // In testing, the limiter is 10/min. 11th request should be throttled.
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($staff)->get('/redeem/token/' . $token->code)->assertStatus(200);
        }

        $this->actingAs($staff)->get('/redeem/token/' . $token->code)->assertStatus(429);
    }
}



