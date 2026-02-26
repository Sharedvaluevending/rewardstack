<?php

namespace Tests\Unit;

use App\Services\QRGeneratorService;
use Tests\TestCase;

class QRGeneratorServiceTest extends TestCase
{
    public function test_normalize_text_design_handles_array_and_null(): void
    {
        $service = app(QRGeneratorService::class);
        $design = [
            'text_top' => [
                'content' => 'Hello',
                'font' => 'Inter',
                'size' => 18,
                'color' => '#111111',
            ],
            'text_bottom' => null,
        ];

        $normalized = $this->invokeNormalize($service, $design);

        $this->assertSame('Hello', $normalized['text_top']);
        $this->assertSame('Inter', $normalized['text_top_font']);
        $this->assertSame(18, $normalized['text_top_size']);
        $this->assertSame('#111111', $normalized['text_top_color']);
        $this->assertSame('', $normalized['text_bottom']);
    }

    public function test_hex_to_rgb_with_short_hex(): void
    {
        $service = app(QRGeneratorService::class);
        $rgb = $this->invokeHexToRgb($service, '#abc');
        $this->assertSame(['r' => 170, 'g' => 187, 'b' => 204], $rgb);
    }

    public function test_hex_to_rgb_invalid_defaults_black(): void
    {
        $service = app(QRGeneratorService::class);
        $rgb = $this->invokeHexToRgb($service, 'zzzzzz');
        $this->assertSame(['r' => 0, 'g' => 0, 'b' => 0], $rgb);
    }

    public function test_get_ecc_level_maps_and_defaults(): void
    {
        $service = app(QRGeneratorService::class);
        $this->assertSame(\chillerlan\QRCode\QRCode::ECC_H, $this->invokeGetEccLevel($service, 'h'));
        $this->assertSame(\chillerlan\QRCode\QRCode::ECC_M, $this->invokeGetEccLevel($service, 'x')); // default
    }

    public function test_make_preview_cache_key_is_stable(): void
    {
        $service = app(QRGeneratorService::class);
        $design = ['size' => 300, 'color' => '#fff'];
        $key1 = $this->invokePreviewKey($service, 'data', $design);
        $key2 = $this->invokePreviewKey($service, 'data', $design);
        $this->assertSame($key1, $key2);
    }

    protected function invokeNormalize(QRGeneratorService $svc, array $design): array
    {
        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('normalizeTextDesign');
        $method->setAccessible(true);
        return $method->invoke($svc, $design);
    }

    protected function invokeHexToRgb(QRGeneratorService $svc, string $hex): array
    {
        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('hexToRgb');
        $method->setAccessible(true);
        return $method->invoke($svc, $hex);
    }

    protected function invokeGetEccLevel(QRGeneratorService $svc, string $level): int
    {
        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('getEccLevel');
        $method->setAccessible(true);
        return $method->invoke($svc, $level);
    }

    protected function invokePreviewKey(QRGeneratorService $svc, string $data, array $design): string
    {
        $ref = new \ReflectionClass($svc);
        $method = $ref->getMethod('makePreviewCacheKey');
        $method->setAccessible(true);
        return $method->invoke($svc, $data, $design);
    }
}

