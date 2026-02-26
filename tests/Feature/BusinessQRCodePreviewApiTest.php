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

        $response->assertStatus(200)->assertJsonStructure(['preview']);
        $preview = $response->json('preview');
        $this->assertIsString($preview);
        $this->assertStringStartsWith('data:image/png;base64,', $preview);
    }
}

