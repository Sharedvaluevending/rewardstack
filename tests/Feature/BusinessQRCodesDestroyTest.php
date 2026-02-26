<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessQRCodesDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_delete_qr_code(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $qr = QRCode::factory()->create(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->delete('/business/qr-codes/' . $qr->id)
            ->assertStatus(302);

        $this->assertSoftDeleted('qr_codes', ['id' => $qr->id]);
    }
}

