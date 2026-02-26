<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Business;
use App\Models\QRCode;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PreviewService
{
    protected ImageManager $imageManager;
    protected PrintfulService $printfulService;

    public function __construct(PrintfulService $printfulService)
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->printfulService = $printfulService;
    }

    /**
     * Generate a preview image for a product with QR code and logo
     */
    public function generatePreview(
        Product $product,
        ?QRCode $qrCode = null,
        ?string $logoUrl = null,
        ?Business $business = null,
        array $options = []
    ): string {
        // If no custom elements, don't generate
        if (!$qrCode && !$logoUrl) {
            throw new \Exception('No custom elements to overlay');
        }

        $view = (string) ($options['view'] ?? 'front');
        $color = $options['color'] ?? null;

        // Get preview configuration (supports multi-view configs)
        $config = $product->getPreviewConfigWithDefaults();
        $configForView = $this->resolveViewConfig($config, $view);

        // Resolve base mockup (local mockup per product/view/color if provided; otherwise Printful/product images)
        $baseSource = $this->resolveBaseMockupSource($product, $view, $color, $options);
        
        \Log::info('Preview generation', [
            'product_id' => $product->id,
            'product_category' => $product->category,
            'view' => $view,
            'color' => $color,
            'base_source' => $baseSource,
            'has_qr' => $qrCode !== null,
            'has_logo' => $logoUrl !== null,
            'config_for_view' => $configForView,
        ]);

        // Debug logging disabled for performance
        // file_put_contents('/var/www/.cursor/debug.log', json_encode([
        //     'id' => 'log_' . time() . '_backend_' . uniqid(),
        //     'timestamp' => time() * 1000,
        //     'location' => 'PreviewService.php:58',
        //     'message' => 'Backend preview generation started',
        //     'data' => [
        //         'product_id' => $product->id,
        //         'view' => $view,
        //         'color' => $color,
        //         'logo_options' => [
        //             'logo_width' => $options['logo_width'] ?? null,
        //             'logo_height' => $options['logo_height'] ?? null,
        //             'logo_x' => $options['logo_x'] ?? null,
        //             'logo_y' => $options['logo_y'] ?? null,
        //         ],
        //         'qr_options' => [
        //             'qr_width' => $options['qr_width'] ?? null,
        //             'qr_height' => $options['qr_height'] ?? null,
        //             'qr_x' => $options['qr_x'] ?? null,
        //             'qr_y' => $options['qr_y'] ?? null,
        //         ],
        //         'config_for_view' => $configForView,
        //     ],
        //     'sessionId' => 'debug-session',
        //     'runId' => 'backend-preview',
        //     'hypothesisId' => 'debug_positioning_system'
        // ]) . "\n", FILE_APPEND);
        // #endregion
        
        try {
            $templateImage = $this->readImageSource($baseSource);
        } catch (\Exception $e) {
            \Log::error('Failed to read base mockup source', [
                'source' => $baseSource,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $canvasWidth = $configForView['canvas']['width'] ?? ($config['canvas']['width'] ?? 800);
        $canvasHeight = $configForView['canvas']['height'] ?? ($config['canvas']['height'] ?? 1000);

        // Resize template to match canvas size
        $templateImage->resize($canvasWidth, $canvasHeight);

        // Add logo if provided
        $renderLogo = array_key_exists('render_logo', $options) ? (bool) $options['render_logo'] : true;
        if ($renderLogo && $logoUrl && !empty($configForView['logo'])) {
            $logoConfig = $configForView['logo'];
            try {
                $logoImage = $this->readImageSource($logoUrl);
                
                // Get logo dimensions - allow live editing overrides
                $logoWidth = (int) ($options['logo_width'] ?? $logoConfig['width'] ?? 550);
                $logoHeight = (int) ($options['logo_height'] ?? $logoConfig['height'] ?? 550);

                // If dimensions are invalid, skip rendering
                if ($logoWidth <= 0 || $logoHeight <= 0) {
                    throw new \Exception('Logo dimensions invalid, skipping');
                }
                
                \Log::info('Logo config loaded', [
                    'config_width' => $logoConfig['width'] ?? 'not set',
                    'config_height' => $logoConfig['height'] ?? 'not set',
                    'final_logo_width' => $logoWidth,
                    'final_logo_height' => $logoHeight,
                ]);

                // Rotate if specified (before resize)
                if (!empty($logoConfig['rotation'])) {
                    $logoImage->rotate($logoConfig['rotation']);
                }

                // Calculate position
                // Try to use Printful API data for accurate placement if available
                $x = null;
                $y = null;
                
                if ($product->printful_product_id && is_numeric($product->printful_product_id)) {
                    try {
                        // Resolve catalog product ID (store products have large IDs)
                        $catalogProductId = (int) $product->printful_product_id;
                        if ($catalogProductId > 1000000) {
                            // This is a store product ID, need to resolve to catalog ID
                            $storeProduct = $this->printfulService->getStoreProduct($catalogProductId);
                            $firstVariant = $storeProduct['sync_variants'][0] ?? null;
                            $catalogProductId = $firstVariant['product']['product_id'] ?? $catalogProductId;
                        }
                        
                        // Map our view to Printful placement type
                        $placementMap = [
                            'front' => 'front',
                            'back' => 'back',
                            'sleeve_left' => 'sleeve_left',
                            'sleeve_right' => 'sleeve_right',
                            'wrap' => 'default',
                        ];
                        $placement = $placementMap[$view] ?? 'front';
                        
                        // Get optimal placement from Printful API
                        $optimalPlacement = $this->printfulService->getOptimalPlacement(
                            $catalogProductId,
                            $placement,
                            $logoWidth,
                            $logoHeight
                        );
                        
                        if ($optimalPlacement) {
                            // Printful gives us printfile dimensions (e.g., 1800x2400)
                            // We use this to understand the print area, but KEEP logo at configured size (450x450)
                            // Only use Printful data to verify our centering calculation
                            
                            // Calculate center position - Printful's left position is already centered
                            // So we can use it to verify, but we'll calculate our own center
                            $x = round(($canvasWidth - $logoWidth) / 2);
                            $y = round(($canvasHeight - $logoHeight) / 2);
                            
                            \Log::info('Using Printful API for verification', [
                                'printfile_area' => $optimalPlacement['area_width'] . 'x' . $optimalPlacement['area_height'],
                                'logo_size' => $logoWidth . 'x' . $logoHeight . ' (KEEPING CONFIG SIZE)',
                                'calculated_x' => $x,
                                'canvas_width' => $canvasWidth,
                                'printful_suggested_left' => $optimalPlacement['left'],
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to get Printful placement data', ['error' => $e->getMessage()]);
                    }
                }
                
                // Allow explicit overrides
                if (array_key_exists('logo_x', $options) && $options['logo_x'] !== null) {
                    $x = (int) $options['logo_x'];
                }
                if (array_key_exists('logo_y', $options) && $options['logo_y'] !== null) {
                    $y = (int) $options['logo_y'];
                }

                // Fallback defaults
                if ($x === null) {
                    $x = (int) round(($canvasWidth - $logoWidth) / 2);
                }
                if ($y === null) {
                    $y = (int) round($canvasHeight * 0.155);
                }
                
                // Resize logo to final dimensions (FORCE size - DO NOT SCALE DOWN)
                // Ensure logo is exactly the size from config (450x450)
                $logoImage->resize($logoWidth, $logoHeight);
                
                \Log::info('Logo placement - FINAL', [
                    'canvas_width' => $canvasWidth,
                    'canvas_height' => $canvasHeight,
                    'logo_width' => $logoWidth,
                    'logo_height' => $logoHeight,
                    'calculated_x' => $x,
                    'calculated_y' => $y,
                    'center_should_be' => round(($canvasWidth - $logoWidth) / 2),
                    'using_printful_data' => isset($optimalPlacement),
                ]);

                // Debug logging disabled for performance
                // file_put_contents('/var/www/.cursor/debug.log', json_encode([
                //     'id' => 'log_' . time() . '_backend_' . uniqid(),
                //     'timestamp' => time() * 1000,
                //     'location' => 'PreviewService.php:178',
                //     'message' => 'Logo final placement calculated',
                //     'data' => [
                //         'canvas' => ['width' => $canvasWidth, 'height' => $canvasHeight],
                //         'logo' => ['width' => $logoWidth, 'height' => $logoHeight, 'x' => $x, 'y' => $y],
                //         'center_calculation' => round(($canvasWidth - $logoWidth) / 2),
                //         'has_printful_data' => isset($optimalPlacement),
                //         'printful_suggested' => $optimalPlacement['left'] ?? null,
                //     ],
                //     'sessionId' => 'debug-session',
                //     'runId' => 'logo-placement',
                //     'hypothesisId' => 'debug_positioning_system'
                // ]) . "\n", FILE_APPEND);
                // #endregion

                // Draw white background behind logo if requested
                if (!empty($options['logo_bg_white'])) {
                    $padding = 10;
                    $bgX = max(0, $x - $padding);
                    $bgY = max(0, $y - $padding);
                    $bgW = min($canvasWidth - $bgX, $logoWidth + ($padding * 2));
                    $bgH = min($canvasHeight - $bgY, $logoHeight + ($padding * 2));
                    $templateImage->drawRectangle($bgX, $bgY, function ($draw) use ($bgW, $bgH) {
                        $draw->size($bgW, $bgH);
                        $draw->background('rgba(255, 255, 255, 1)');
                    });
                }

                // Overlay logo on template (centered horizontally)
                $templateImage->place(
                    $logoImage,
                    'top-left',
                    $x,
                    $y
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to overlay logo', [
                    'logo_url' => $logoUrl,
                    'error' => $e->getMessage(),
                ]);
                // Logo failed to load, continue without it
            }
        }

        // Add primary QR code if provided
        $renderQr = array_key_exists('render_qr', $options) ? (bool) $options['render_qr'] : true;
        
        if ($renderQr && $qrCode) {
            // If no QR config for this view, use default config
            if (empty($configForView['qr_code'])) {
                $configForView['qr_code'] = $config['qr_code'] ?? ['width' => 250, 'height' => 250];
            }
            
            if (!empty($configForView['qr_code'])) {
                $qrConfig = $configForView['qr_code'];

                // Generate QR code image
                $qrGenerator = new QRGeneratorService();
                $qrImageData = $qrGenerator->generate(
                    $qrCode->getScanUrl(),
                    $qrCode->getDesignWithDefaults()
                );

                // Convert base64 to image
                $qrImageBinary = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $qrImageData));
                $qrImage = $this->imageManager->read($qrImageBinary);

                // Resize QR code - allow live editing overrides
                $qrWidth = (int) ($options['qr_width'] ?? $qrConfig['width'] ?? 400);
                $qrHeight = (int) ($options['qr_height'] ?? $qrConfig['height'] ?? 400);

                // If dimensions are invalid, skip rendering
                if ($qrWidth <= 0 || $qrHeight <= 0) {
                    return $this->savePreviewAndReturnPath($templateImage, $product, $qrCode, $view);
                }
                
                $qrImage->resize($qrWidth, $qrHeight);

                // Rotate if specified
                if (!empty($qrConfig['rotation'])) {
                    $qrImage->rotate($qrConfig['rotation']);
                }

                // Use position from options (set by controller) if available, otherwise use config
                $x = $options['qr_x'] ?? $qrConfig['x'] ?? null;
                $y = $options['qr_y'] ?? $qrConfig['y'] ?? null;

                // Allow live editing overrides for QR position
                if (isset($options['qr_x']) && $options['qr_x'] !== null) {
                    $x = (int) $options['qr_x'];
                } elseif (in_array($view, ['sleeve_left', 'sleeve_right'])) {
                    // For sleeve views, ALWAYS center horizontally
                    $x = round(($canvasWidth - $qrWidth) / 2);
                } elseif ($x === null) {
                    // Default positioning for front view
                    $x = 50;
                }

                if (isset($options['qr_y']) && $options['qr_y'] !== null) {
                    $y = (int) $options['qr_y'];
                } elseif ($y === null) {
                    $y = 350;
                }

                // Overlay primary QR code on template
                $templateImage->place(
                    $qrImage,
                    'top-left',
                    $x,
                    $y
                );
            }
        }

        // Add secondary QR code if configured (for double QR designs)
        if ($qrCode && !empty($configForView['qr_code_2'])) {
            $qrConfig2 = $configForView['qr_code_2'];

            // Generate QR code image
            $qrGenerator = new QRGeneratorService();
            $qrImageData = $qrGenerator->generate(
                $qrCode->getScanUrl(),
                $qrCode->getDesignWithDefaults()
            );

            // Convert base64 to image
            $qrImageBinary = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $qrImageData));
            $qrImage2 = $this->imageManager->read($qrImageBinary);

            // Resize QR code
            $qrImage2->resize(
                $qrConfig2['width'] ?? 100,
                $qrConfig2['height'] ?? 100
            );

            // Rotate if specified
            if (!empty($qrConfig2['rotation'])) {
                $qrImage2->rotate($qrConfig2['rotation']);
            }

            // Overlay secondary QR code on template
            $templateImage->place(
                $qrImage2,
                'top-left',
                $qrConfig2['x'] ?? 600,
                $qrConfig2['y'] ?? 350
            );
        }

        return $this->savePreviewAndReturnPath($templateImage, $product, $qrCode, $view);
    }

    private function savePreviewAndReturnPath($templateImage, Product $product, ?QRCode $qrCode, string $view): string
    {
        // Generate filename and save
        $filename = 'preview_' . $product->id . '_' . ($qrCode ? $qrCode->id : 'no-qr') . '_' . $view . '_' . time() . '.png';
        $path = 'previews/' . $filename;

        // Save to storage
        try {
            // Intervention Image v3: use toPng() method which returns EncodedImage, then get binary
            $pngData = $templateImage->toPng()->toString();
            Storage::disk('public')->put($path, $pngData);
            \Log::info('Preview image saved', ['path' => $path]);
        } catch (\Exception $e) {
            \Log::error('Failed to save preview image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $path;
    }

    private function resolveViewConfig(array $config, string $view): array
    {
        $view = $view ?: 'front';
        if (!empty($config['views']) && is_array($config['views'])) {
            if (!empty($config['views'][$view]) && is_array($config['views'][$view])) {
                return $config['views'][$view];
            }
            if (!empty($config['views']['front']) && is_array($config['views']['front'])) {
                return $config['views']['front'];
            }
        }

        return $config;
    }

    private function resolveBaseMockupSource(Product $product, string $view, ?string $color, array $options = []): string
    {
        $normalizedColor = null;

        // FOR PRINTFUL PRODUCTS: ALWAYS use ghost templates (product-only, no human model).
        // This guarantees sleeve/back images exist and NO models.
        if ($product->printful_product_id && is_numeric($product->printful_product_id)) {
            $printfulId = (int) $product->printful_product_id;

            // printful_product_id may be either:
            // - Catalog product id (small)
            // - Store product id (very large). In that case resolve to catalog product id for template APIs.
            $catalogProductId = $printfulId;
            if ($printfulId > 1000000) {
                try {
                    $storeProduct = $this->printfulService->getStoreProduct($printfulId);
                    $firstSyncVariant = $storeProduct['sync_variants'][0] ?? null;
                    if ($firstSyncVariant && isset($firstSyncVariant['product']['product_id'])) {
                        $catalogProductId = (int) $firstSyncVariant['product']['product_id'];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to resolve store product to catalog product', [
                        'product_id' => $product->id,
                        'store_product_id' => $printfulId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $variantId = null;

            // Prefer explicit variant_id, otherwise pick from stored product variants (fast, avoids API)
            if (isset($options['variant_id']) && is_numeric($options['variant_id'])) {
                $variantId = (int) $options['variant_id'];
            } else {
                // Use first stored variant id (from products.variants JSON) if present
                if (!empty($product->variants) && is_array($product->variants)) {
                    $firstSize = $product->variants[0] ?? null;
                    $firstVariantId = $firstSize['variant_ids'][0] ?? ($firstSize['colors'][0]['variant_id'] ?? null);
                    if ($firstVariantId && is_numeric($firstVariantId)) {
                        $variantId = (int) $firstVariantId;
                    }
                }

                // Final fallback: fetch first variant from Printful catalog API
                if (!$variantId) {
                    try {
                        $variants = $this->printfulService->getProductVariants($catalogProductId);
                        $firstVariant = $variants['variants'][0] ?? null;
                        if ($firstVariant && isset($firstVariant['id'])) {
                            $variantId = (int) $firstVariant['id'];
                            \Log::info('Fetched variant_id from Printful API', [
                                'product_id' => $product->id,
                                'variant_id' => $variantId,
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Failed to fetch variant_id from Printful', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            if ($variantId && in_array($view, ['front', 'back', 'sleeve_left', 'sleeve_right'], true)) {
                try {
                    // If color wasn't provided, derive it from the chosen variant_id (prevents "default" cache paths).
                    if (empty($color) && $variantId) {
                        // Prefer stored variants (fast)
                        if (!empty($product->variants) && is_array($product->variants)) {
                            foreach ($product->variants as $variant) {
                                foreach (($variant['colors'] ?? []) as $colorObj) {
                                    if (($colorObj['variant_id'] ?? null) == $variantId) {
                                        $color = $colorObj['name'] ?? null;
                                        break 2;
                                    }
                                }
                            }
                        }

                        // Final fallback: ask Printful for the variant's color
                        if (empty($color)) {
                            try {
                                $variantsResp = $this->printfulService->getProductVariants($catalogProductId);
                                foreach (($variantsResp['variants'] ?? []) as $v) {
                                    if (($v['id'] ?? null) == $variantId) {
                                        $color = $v['color'] ?? null;
                                        break;
                                    }
                                }
                            } catch (\Exception $e) {
                                // ignore, we'll fall back to default below
                            }
                        }
                    }

                    $normalizedColor = $this->normalizeColorName((string) ($color ?: 'default'));

                    $slug = $this->getProductSlug($product);
                    \Log::info('Attempting ghost template fetch', [
                        'product_id' => $product->id,
                        'catalog_product_id' => $catalogProductId,
                        'variant_id' => $variantId,
                        'view' => $view,
                        'slug' => $slug,
                        'color_normalized' => $normalizedColor,
                    ]);
                    
                    $cachedRelPath = $this->printfulService->getOrCacheGhostTemplate(
                        $catalogProductId,
                        $variantId,
                        $slug,
                        $normalizedColor,
                        (string) $product->category,
                        $view,
                        30
                    );

                    if ($cachedRelPath) {
                        $abs = storage_path('app/public/' . $cachedRelPath);
                        if (file_exists($abs)) {
                            \Log::info('✅ Using ghost template (no model)', [
                                'product_id' => $product->id,
                                'view' => $view,
                                'variant_id' => $variantId,
                                'path' => $abs,
                            ]);
                            return $abs;
                        } else {
                            \Log::warning('Ghost template path returned but file missing', [
                                'product_id' => $product->id,
                                'view' => $view,
                                'path' => $abs,
                            ]);
                        }
                    } else {
                        \Log::warning('❌ Ghost template caching returned null', [
                            'product_id' => $product->id,
                            'view' => $view,
                            'variant_id' => $variantId,
                            'catalog_product_id' => $catalogProductId,
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Ghost template caching failed', [
                        'product_id' => $product->id,
                        'view' => $view,
                        'variant_id' => $variantId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        // For Printful products, ONLY use ghost templates (already attempted above).
        // Do NOT fall back to lifestyle mockups (they have human models).
        // If ghost template failed, log it and continue to non-Printful fallbacks below.
        if ($product->printful_product_id) {
            \Log::error('Printful product ghost template failed - no fallback to avoid human models', [
                'product_id' => $product->id,
                'printful_product_id' => $product->printful_product_id,
                'view' => $view,
                'variant_id' => $options['variant_id'] ?? null,
                'color' => $color,
            ]);
        }

        // Fallback: local mockups on disk (no-person flat mockups)
        $slug = $product->slug ?: ('product-' . $product->id);
        $view = $view ?: 'front';

        if ($normalizedColor === null) {
            $normalizedColor = $this->normalizeColorName((string) ($color ?: 'default'));
        }

        $candidates = [];
        if ($color || $normalizedColor) {
            $candidates[] = storage_path("app/public/mockups/{$slug}/{$normalizedColor}/{$view}.png");
            $candidates[] = storage_path("app/public/mockups/{$slug}/{$normalizedColor}/{$view}.jpg");
            // For sleeve views, also try front view as fallback (same base product)
            if ($view !== 'front' && in_array($view, ['sleeve_left', 'sleeve_right', 'back'])) {
                $candidates[] = storage_path("app/public/mockups/{$slug}/{$normalizedColor}/front.png");
                $candidates[] = storage_path("app/public/mockups/{$slug}/{$normalizedColor}/front.jpg");
            }
        }
        $candidates[] = storage_path("app/public/mockups/{$slug}/{$view}.png");
        $candidates[] = storage_path("app/public/mockups/{$slug}/{$view}.jpg");
        // For sleeve views, also try front view as fallback
        if ($view !== 'front' && in_array($view, ['sleeve_left', 'sleeve_right', 'back'])) {
            $candidates[] = storage_path("app/public/mockups/{$slug}/front.png");
            $candidates[] = storage_path("app/public/mockups/{$slug}/front.jpg");
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback: existing product images (Printful) - BUT ONLY if NOT a Printful product
        // (Printful products MUST use ghost templates above, never lifestyle/model images)
        if (!$product->printful_product_id) {
            $images = array_values(array_filter($product->images ?? []));
            if (!empty($images)) {
                return (string) $images[0];
            }
        } else {
            // For Printful products, if we got here, ghost template failed - log it
            \Log::error('Printful product fell through to image fallback (should not happen)', [
                'product_id' => $product->id,
                'printful_product_id' => $product->printful_product_id,
                'view' => $view,
                'variant_id' => $options['variant_id'] ?? null,
            ]);
        }

        // Last resort: template image per category
        $templatePath = storage_path('app/public/products/templates/' . $product->category . '-blank.png');
        if (file_exists($templatePath)) {
            return $templatePath;
        }
        
        // Ultimate fallback: use a generic template
        return storage_path('app/public/products/templates/t-shirt-blank.png');
    }

    private function normalizeColorName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        return trim($name, '-');
    }

    /**
     * Read an image from a local path, /storage path, or remote URL.
     * Some environments don't allow direct remote file reads; we fetch remote URLs first.
     */
    private function readImageSource(string $source)
    {
        $source = trim($source);

        // Convert /storage/... URL to filesystem path
        if (str_starts_with($source, '/storage/')) {
            $relative = ltrim(substr($source, strlen('/storage/')), '/');
            return $this->imageManager->read(storage_path('app/public/' . $relative));
        }

        // Remote URL
        if (preg_match('#^https?://#i', $source)) {
            $tmp = tempnam(sys_get_temp_dir(), 'img_');
            try {
                $resp = Http::timeout(20)->get($source);
                if (!$resp->ok()) {
                    throw new \Exception("Failed to fetch image URL ({$resp->status()}): {$source}");
                }
                file_put_contents($tmp, $resp->body());
                return $this->imageManager->read($tmp);
            } finally {
                if ($tmp && file_exists($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        // Local filesystem path
        return $this->imageManager->read($source);
    }

    /**
     * Generate preview URL for frontend
     */
    public function getPreviewUrl(
        Product $product,
        ?QRCode $qrCode = null,
        ?string $logoUrl = null,
        ?Business $business = null,
        array $options = []
    ): string {
        // If we have something to overlay, always try generating a custom preview (template or Printful product)
        if ($qrCode || $logoUrl) {
            try {
                $path = $this->generatePreview($product, $qrCode, $logoUrl, $business, $options);
                return '/storage/' . $path;
            } catch (\Exception $e) {
                \Log::error('Preview generation failed', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Fall through to best-effort default image
            }
        }

        // Default image preference: Printful/product images first, otherwise template
        if (!empty($product->images) && count($product->images) > 0) {
            return $product->images[0];
        }

        return $product->getPreviewTemplateUrl() ?? asset('images/product-placeholder.png');
    }

    /**
     * Get preview data for API response
     */
    public function getPreviewData(
        Product $product,
        ?QRCode $qrCode = null,
        ?Business $business = null,
        array $options = []
    ): array {
        $logoUrl = $business ? $business->logo_url : null;

        return [
            'preview_url' => $this->getPreviewUrl($product, $qrCode, $logoUrl, $business, $options),
            'config' => $this->resolveViewConfig($product->getPreviewConfigWithDefaults(), (string) ($options['view'] ?? 'front')),
            'has_qr' => $qrCode !== null,
            'has_logo' => $logoUrl !== null,
            'view' => (string) ($options['view'] ?? 'front'),
            'color' => $options['color'] ?? null,
        ];
    }

    /**
     * Get standardized product slug for caching
     */
    private function getProductSlug(Product $product): string
    {
        // Use known slugs for common Printful products
        $knownSlugs = [
            71 => 'unisex-staple-t-shirt-bella-canvas-3001',
            380 => 'cotton-heritage-m2580-premium-pullover-hoodie',
            146 => 'unisex-heavy-blend-hoodie-gildan-18500',
            959 => 'unisex-ringer-t-shirt-next-level-3604', // Next Level 3604
        ];

        $productId = (int) $product->printful_product_id;
        if (isset($knownSlugs[$productId])) {
            return $knownSlugs[$productId];
        }

        // Fallback to product slug or generated slug
        return $product->slug ?: ('product-' . $product->id);
    }

    /**
     * Check if a URL exists (for template images)
     */
    private function urlExists(string $url): bool
    {
        // Convert asset URL to local path if it's a local file
        if (str_contains($url, asset(''))) {
            $path = str_replace(asset('storage/'), '', $url);
            $fullPath = storage_path('app/public/' . $path);
            return file_exists($fullPath);
        }

        // For external URLs, try a HEAD request
        $headers = @get_headers($url);
        return $headers && strpos($headers[0], '200') !== false;
    }
}
