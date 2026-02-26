<?php

namespace Tests\Unit;

use App\Services\QRGeneratorService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QRGeneratorServiceCoverageTest extends TestCase
{
    public function test_generate_returns_png_data_uri_in_preview_mode(): void
    {
        /** @var QRGeneratorService $service */
        $service = app(QRGeneratorService::class);

        $out = $service->generate('https://example.test/x', [
            'size' => 160,
            'margin' => 4,
            'preview_mode' => true,
        ]);

        $this->assertIsString($out);
        $this->assertStringStartsWith('data:image/png;base64,', $out);
    }

    public function test_generate_uses_preview_cache_for_same_input(): void
    {
        Cache::flush();

        /** @var QRGeneratorService $service */
        $service = app(QRGeneratorService::class);

        $design = [
            // Force preview scaling branch (size reduced to 360 internally)
            'size' => 600,
            'margin' => 10,
            'preview_mode' => true,
            'module_shape' => 'dots',
            'finder_shape' => 'circle',
            'background_gradient' => [
                'type' => 'linear',
                'angle' => 45,
                'colors' => [
                    ['color' => '#111111', 'position' => 0],
                    ['color' => '#222222', 'position' => 100],
                ],
            ],
        ];

        $out1 = $service->generate('PREVIEW-CACHE', $design);
        $out2 = $service->generate('PREVIEW-CACHE', $design);

        $this->assertIsString($out1);
        $this->assertSame($out1, $out2);
    }

    public function test_generate_non_preview_can_apply_shadow_and_returns_data_uri(): void
    {
        /** @var QRGeneratorService $service */
        $service = app(QRGeneratorService::class);

        $out = $service->generate('SHADOW', [
            'size' => 180,
            'margin' => 4,
            'preview_mode' => false,
            'module_shape' => 'diamond',
            'finder_shape' => 'rounded',
            'shadow' => [
                'color' => '#000000',
                'opacity' => 0.2,
                'blur' => 2,
                'offsetX' => 1,
                'offsetY' => 1,
            ],
        ]);

        $this->assertIsString($out);
        $this->assertStringStartsWith('data:image/png;base64,', $out);
    }
}

