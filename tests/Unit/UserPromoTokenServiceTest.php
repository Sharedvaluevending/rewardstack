<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\QRCode;
use App\Models\User;
use App\Services\UserPromoTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPromoTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_returns_null_when_user_role_is_not_customer_or_user(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create(['role' => 'business']);
        $qrCode = QRCode::factory()->create(['business_id' => $business->id]);

        $service = app(UserPromoTokenService::class);
        $result = $service->ensure($user, $qrCode);

        $this->assertNull($result);
    }
}
