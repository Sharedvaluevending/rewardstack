<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\QRCode;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BusinessQRCodesValidationLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_exclusive_requires_required_level(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Storage::fake('public');
        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')->andReturn('qr/test.png');
        $this->app->instance(QRGeneratorService::class, $qrMock);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'Level QR',
                'type' => 'level_exclusive',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['required_level']);
    }

    public function test_cross_promo_requires_cross_promotion_id(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Storage::fake('public');
        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')->andReturn('qr/test.png');
        $this->app->instance(QRGeneratorService::class, $qrMock);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'Cross Promo QR',
                'type' => 'cross_promo',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['cross_promotion_id']);
    }

    public function test_qr_code_plan_limit_is_enforced(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Storage::fake('public');
        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')->andReturn('qr/test.png');
        $this->app->instance(QRGeneratorService::class, $qrMock);

        $business = Business::factory()->create([
            'is_testing_account' => true,
            'subscription_tier' => 'starter', // seeded starter limit: qr_codes = 10
        ]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        QRCode::factory()->count(10)->create([
            'business_id' => $business->id,
        ]);

        $this->assertSame(10, QRCode::where('business_id', $business->id)->count());

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'Over limit',
                'type' => 'static',
                'destination_url' => 'https://example.com',
                'is_active' => true,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['limit']);

        $this->assertSame(10, QRCode::where('business_id', $business->id)->count());
    }
}

