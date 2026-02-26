<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\QRCode;
use App\Services\PrintKitArtworkService;
use App\Services\QRGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PrintKitArtworkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createMinimalPng(): string
    {
        $img = @imagecreate(2, 2);
        if ($img === false) {
            $this->markTestSkipped('GD not available');
        }
        imagecolorallocate($img, 255, 255, 255);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        return $png;
    }

    public function test_generate2_in_sticker_sheet_calls_qr_generator_and_stores_sheet(): void
    {
        Storage::fake('public');
        $png = $this->createMinimalPng();
        Storage::disk('public')->put('qr/sheet-qr.png', $png);

        $qrCode = QRCode::factory()->create([
            'business_id' => Business::factory()->create()->id,
            'code' => 'TESTCODE',
        ]);

        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(function (array $opts) {
                    return isset($opts['size'], $opts['preview_mode']) && $opts['size'] === 600 && $opts['preview_mode'] === true;
                }),
                'png'
            )
            ->andReturn('qr/sheet-qr.png');

        $service = new PrintKitArtworkService($qrMock);
        $path = $service->generate2InStickerSheet($qrCode);

        $this->assertStringStartsWith('printkits/sheets/' . $qrCode->business_id . '/sheet_' . $qrCode->code . '_', $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $content = Storage::disk('public')->get($path);
        $this->assertNotEmpty($content);
        $this->assertSame("\x89PNG", substr($content, 0, 4));
    }

    public function test_generate4_in_decal_calls_qr_generator_and_copies_to_decals(): void
    {
        Storage::fake('public');
        $png = $this->createMinimalPng();
        Storage::disk('public')->put('qr/decal-qr.png', $png);

        $business = Business::factory()->create();
        $qrCode = QRCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'DECAL01',
        ]);

        $qrMock = Mockery::mock(QRGeneratorService::class);
        $qrMock->shouldReceive('generateFile')
            ->once()
            ->with(
                Mockery::type('string'),
                Mockery::on(function (array $opts) {
                    return isset($opts['size'], $opts['preview_mode']) && $opts['size'] === 1200 && $opts['preview_mode'] === true;
                }),
                'png'
            )
            ->andReturn('qr/decal-qr.png');

        $service = new PrintKitArtworkService($qrMock);
        $path = $service->generate4InDecal($qrCode);

        $this->assertStringStartsWith('printkits/decals/' . $qrCode->business_id . '/decal_' . $qrCode->code . '_', $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame($png, Storage::disk('public')->get($path));
    }
}
