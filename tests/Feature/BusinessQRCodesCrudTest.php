<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class BusinessQRCodesCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_codes_index_create_show_edit_load_and_store_update_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $promo = Promotion::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        // Mock QR file generation so tests don't touch real filesystem
        Storage::fake('public');
        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')->andReturn('qr/test.png');
        $qrMock->shouldReceive('generate')->andReturn('data:image/png;base64,AAAA');
        $this->app->instance(QRGeneratorService::class, $qrMock);
        Storage::disk('public')->put('qr/test.png', 'fake');

        $this->actingAs($owner)
            ->get('/business/qr-codes')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRCodes/Index'));

        $this->actingAs($owner)
            ->get('/business/qr-codes/create')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRCodes/Create'));

        $this->actingAs($owner)
            ->post('/business/qr-codes', [
                'name' => 'My Promo QR',
                'type' => 'promotion',
                'promotion_id' => $promo->id,
                'design' => ['module_color' => '#ff0000'],
                'is_active' => true,
            ])
            ->assertStatus(302);

        $qr = QRCode::where('business_id', $business->id)->first();
        $this->assertNotNull($qr);
        $this->assertSame('promotion', $qr->type);
        $this->assertSame($promo->id, $qr->promotion_id);

        $this->actingAs($owner)
            ->get('/business/qr-codes/' . $qr->id)
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRCodes/Show'));

        $this->actingAs($owner)
            ->get('/business/qr-codes/' . $qr->id . '/edit')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page->component('Business/QRCodes/Edit'));

        $this->actingAs($owner)
            ->put('/business/qr-codes/' . $qr->id, [
                'name' => 'Updated Name',
                'destination_url' => 'https://example.com',
                'design' => ['module_color' => '#00ff00'],
                'is_active' => true,
            ])
            ->assertStatus(302);

        $qr->refresh();
        $this->assertSame('Updated Name', $qr->name);
        $this->assertSame('https://example.com', $qr->destination_url);
    }

    public function test_duplicate_and_download_work(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Storage::fake('public');
        Storage::disk('public')->put('qr/test.png', 'fake');
        Storage::disk('public')->put('qr/download.png', 'fake-download');

        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')->andReturnUsing(function ($data, $design, $format = 'png') {
            return $format === 'png' ? 'qr/download.png' : 'qr/download.png';
        });
        $qrMock->shouldReceive('generate')->andReturn('data:image/png;base64,AAAA');
        $this->app->instance(QRGeneratorService::class, $qrMock);

        $business = Business::factory()->create(['is_testing_account' => true]);
        $owner = $business->owner;
        $owner->update(['role' => 'business']);

        $qr = QRCode::factory()->create([
            'business_id' => $business->id,
            'type' => 'static',
            'destination_url' => 'https://example.com',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post("/business/qr-codes/{$qr->id}/duplicate")
            ->assertStatus(302);

        $this->assertSame(2, QRCode::where('business_id', $business->id)->count());

        $copy = QRCode::where('business_id', $business->id)
            ->where('id', '!=', $qr->id)
            ->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($qr->code, $copy->code);

        $resp = $this->actingAs($owner)->get("/business/qr-codes/{$qr->id}/download/png");
        $resp->assertStatus(200);
        $resp->assertHeader('content-disposition');
    }
}

