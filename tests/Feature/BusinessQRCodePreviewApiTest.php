<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessQRCodePreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_preview_api_returns_preview(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $response = $this->actingAs($owner)
            ->postJson('/api/qr/preview', [
                'data' => 'https://example.com',
                'design' => [
                    'size' => 160,
                    'margin' => 4,
                    'preview_mode' => true,
                    'module_color' => '#ff0000',
                ],
            ]);

        // API returns raw PNG binary (not JSON) for performance
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
        $png = $response->getContent();
        $this->assertNotEmpty($png);
        $this->assertStringStartsWith("\x89PNG", $png, 'Response should be valid PNG binary');
    }
}

