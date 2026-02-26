<?php

namespace App\Services;

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Data\QRMatrix;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class QRGeneratorService
{
    protected ImageManager $imageManager;
    protected array $gradientCache = [];

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Normalize text config so generator supports BOTH formats:
     * - legacy: text_top/text_bottom are strings + text_*_font/size/color fields
     * - new: text_top/text_bottom are arrays like {content,font,size,color}
     */
    protected function normalizeTextDesign(array $design): array
    {
        foreach (['top', 'bottom'] as $pos) {
            $key = "text_{$pos}";
            $prefix = "text_{$pos}_";

            if (!array_key_exists($key, $design)) {
                continue;
            }

            if (is_array($design[$key])) {
                $obj = $design[$key];
                $design[$key] = (string)($obj['content'] ?? '');

                if (!empty($obj['font'])) {
                    $design[$prefix . 'font'] = (string)$obj['font'];
                }
                if (isset($obj['size'])) {
                    $design[$prefix . 'size'] = (int)$obj['size'];
                }
                if (!empty($obj['color'])) {
                    $design[$prefix . 'color'] = (string)$obj['color'];
                }
            } elseif ($design[$key] === null) {
                $design[$key] = '';
            }
        }

        return $design;
    }

    /**
     * Generate a QR code with custom design options
     * Draws everything manually from matrix for perfect alignment
     */
    public function generate(string $data, array $design = []): string
    {
        $defaults = [
            'size' => 300,
            'margin' => 4,
            'error_correction' => 'H',
            'module_shape' => 'square',
            'finder_shape' => 'square',
            'background_color' => '#FFFFFF',
            'background_gradient' => null,
            'module_color' => '#000000',
            'module_gradient' => null,
            'finder_color' => null,
            'logo' => null,
            'logo_size' => 0.25,
            'logo_background' => false,
            'logo_border_radius' => 0,
            'text_top' => '',
            'text_top_font' => 'Inter',
            'text_top_size' => 16,
            'text_top_color' => '#000000',
            'text_top_outline' => false,
            'text_top_outline_color' => '#FFFFFF',
            'text_top_outline_width' => 2,
            'text_bottom' => '',
            'text_bottom_font' => 'Inter',
            'text_bottom_size' => 16,
            'text_bottom_color' => '#000000',
            'text_bottom_outline' => false,
            'text_bottom_outline_color' => '#FFFFFF',
            'text_bottom_outline_width' => 2,
            'border' => null,
            'glow' => null,
            'shadow' => null,
            'preview_mode' => false, // Scale effects in preview for speed
        ];

        $design = array_merge($defaults, $design);
        $design = $this->normalizeTextDesign($design);
        
        // Check if we're in preview mode (skip expensive effects for speed)
        $isPreview = !empty($design['preview_mode']);
        $previewScale = null;
        if ($isPreview) {
            // Render preview at higher resolution for crisp/sharp text rendering.
            // GD's imagettftext produces poor antialiasing at small font sizes on small
            // canvases. By rendering at a minimum of 700px (2x typical display size),
            // we get supersampled text that the browser downscales sharply.
            $minPreviewSize = 700;
            $maxPreviewSize = 900;
            $currentSize = (int)($design['size'] ?? 300);

            if ($currentSize < $minPreviewSize) {
                // Scale UP small canvases for crisp text
                $previewScale = $minPreviewSize / $currentSize;
                $design['size'] = $minPreviewSize;
            } elseif ($currentSize > $maxPreviewSize) {
                // Scale DOWN very large canvases for performance
                $previewScale = $maxPreviewSize / $currentSize;
                $design['size'] = $maxPreviewSize;
            }

            if ($previewScale !== null) {
                // Scale proportional properties so preview matches final proportions
                $design['margin'] = max(1, (int) round(($design['margin'] ?? 4) * $previewScale));
                foreach (['top', 'bottom'] as $pos) {
                    $sizeKey = "text_{$pos}_size";
                    $outlineKey = "text_{$pos}_outline_width";
                    if (isset($design[$sizeKey])) {
                        $design[$sizeKey] = max(12, (int) round($design[$sizeKey] * $previewScale));
                    }
                    if (isset($design[$outlineKey])) {
                        $design[$outlineKey] = max(1, (int) round($design[$outlineKey] * $previewScale));
                    }
                }
            }
        }

        if ($previewScale !== null) {
            if (!empty($design['border']) && is_array($design['border'])) {
                if (isset($design['border']['width'])) {
                    $design['border']['width'] = max(1, (int) round($design['border']['width'] * $previewScale));
                }
                if (isset($design['border']['radius'])) {
                    $design['border']['radius'] = max(0, (int) round($design['border']['radius'] * $previewScale));
                }
            }
            if (!empty($design['glow']) && is_array($design['glow'])) {
                if (isset($design['glow']['intensity'])) {
                    $design['glow']['intensity'] = max(0, (int) round($design['glow']['intensity'] * $previewScale));
                }
                if (isset($design['glow']['spread'])) {
                    $design['glow']['spread'] = max(0, (int) round($design['glow']['spread'] * $previewScale));
                }
            }
            if (!empty($design['shadow']) && is_array($design['shadow'])) {
                if (isset($design['shadow']['blur'])) {
                    $design['shadow']['blur'] = max(0, (int) round($design['shadow']['blur'] * $previewScale));
                }
                if (isset($design['shadow']['offsetX'])) {
                    $design['shadow']['offsetX'] = (int) round($design['shadow']['offsetX'] * $previewScale);
                }
                if (isset($design['shadow']['offsetY'])) {
                    $design['shadow']['offsetY'] = (int) round($design['shadow']['offsetY'] * $previewScale);
                }
            }
        }

        // Cache rapid-fire previews by design+data hash
        $previewCacheKey = null;
        if ($isPreview) {
            $previewCacheKey = $this->makePreviewCacheKey($data, $design);
            if (Cache::has($previewCacheKey)) {
                return Cache::get($previewCacheKey);
            }
        }

        // Get shapes IMMEDIATELY after merge to ensure they're correct
        $moduleShape = !empty($design['module_shape']) ? $design['module_shape'] : 'square';
        $finderShape = !empty($design['finder_shape']) ? $design['finder_shape'] : 'square';
        
        // Get colors
        $moduleColor = $this->hexToRgb($design['module_color'] ?: '#000000');
        $finderColor = !empty($design['finder_color']) 
            ? $this->hexToRgb($design['finder_color']) 
            : $moduleColor;
        $bgColor = $this->hexToRgb($design['background_color'] ?: '#FFFFFF');

        // Calculate text space requirements using actual font metrics
        $topTextHeight = 0;
        $bottomTextHeight = 0;
        if (!empty($design['text_top'])) {
            $topTextHeight = $this->measureTextAreaHeight(
                $design['text_top'],
                (int)($design['text_top_size'] ?? 16),
                $design['text_top_font'] ?? 'Inter'
            );
        }
        if (!empty($design['text_bottom'])) {
            $bottomTextHeight = $this->measureTextAreaHeight(
                $design['text_bottom'],
                (int)($design['text_bottom_size'] ?? 16),
                $design['text_bottom_font'] ?? 'Inter'
            );
        }

        $baseSize = (int) $design['size'];
        $quietZone = (int) $design['margin'];
        $qrOffsetY = 0;

        // Get QR matrix with proper quiet zone (QR standard requires 4 modules)
        // IMPORTANT: Must use outputType and call render() first to get properly sized matrix
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'eccLevel' => $this->getEccLevel($design['error_correction']),
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'quietzoneSize' => 4, // QR standard - required for reliable scanning
            'imageBase64' => false,
        ]);
        $qrcode = new QRCode($options);
        // Must call render() first to initialize proper matrix with quiet zone
        $qrcode->render($data);
        $matrix = $qrcode->getQRMatrix($data);
        $moduleCount = $matrix->getSize();
        
        // Calculate module size to fit target size with margin
        $availableSize = $baseSize - ($quietZone * 2);
        $moduleSize = (int)floor($availableSize / $moduleCount);
        
        // Actual QR area size
        $qrAreaSize = $moduleCount * $moduleSize;
        $actualQrSize = $qrAreaSize + ($quietZone * 2);
        $marginPx = $quietZone;
        
        // QR canvas is just the QR code (text added outside border later)
        $qrCanvasHeight = $actualQrSize;

        // Create canvas with GD (just for QR code, not text)
        $canvasGd = imagecreatetruecolor($actualQrSize, (int)$qrCanvasHeight);
        
        // Create gradient images if needed
        $bgGradGd = null;
        $moduleGradGd = null;
        
        if (!empty($design['background_gradient'])) {
            $bgGradImage = $this->createGradientImage($actualQrSize, (int)$qrCanvasHeight, $design['background_gradient']);
            $bgGradGd = $bgGradImage->core()->native();
            // Fill canvas with gradient
            imagecopy($canvasGd, $bgGradGd, 0, 0, 0, 0, $actualQrSize, (int)$qrCanvasHeight);
        } else {
            // Fill with solid background
            $bgGdColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
            imagefilledrectangle($canvasGd, 0, 0, $actualQrSize - 1, (int)$qrCanvasHeight - 1, $bgGdColor);
        }
        
        if (!empty($design['module_gradient'])) {
            $moduleGradImage = $this->createGradientImage($actualQrSize, $actualQrSize, $design['module_gradient']);
            $moduleGradGd = $moduleGradImage->core()->native();
        }
        
        // Quiet zone offset (matrix includes 4-module border for QR standard)
        $qzOffset = 4;
        
        // STEP 1: Draw ALL modules from matrix first (ensures scannability)
        // This draws the complete QR code including finder patterns as squares
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                $isDark = $matrix->check($col, $row);
                
                $x1 = $marginPx + ($col * $moduleSize);
                $y1 = $qrOffsetY + $marginPx + ($row * $moduleSize);
                $x2 = $x1 + $moduleSize - 1;
                $y2 = $y1 + $moduleSize - 1;
                $cx = $x1 + ($moduleSize / 2);
                $cy = $y1 + ($moduleSize / 2);
                
                // Check if this is a finder pattern area
                $isTopLeftFinder = ($col >= $qzOffset && $col < $qzOffset + 7 && $row >= $qzOffset && $row < $qzOffset + 7);
                $isTopRightFinder = ($col >= $moduleCount - 7 - $qzOffset && $col < $moduleCount - $qzOffset && $row >= $qzOffset && $row < $qzOffset + 7);
                $isBottomLeftFinder = ($col >= $qzOffset && $col < $qzOffset + 7 && $row >= $moduleCount - 7 - $qzOffset && $row < $moduleCount - $qzOffset);
                $isFinderArea = $isTopLeftFinder || $isTopRightFinder || $isBottomLeftFinder;
                
                if ($isDark) {
                    // Get color (gradient or solid)
                    // IMPORTANT: finder patterns should always respect finder_color (even when module_gradient is enabled)
                    if ($isFinderArea) {
                        $color = $finderColor;
                        $modColor = imagecolorallocate($canvasGd, $color['r'], $color['g'], $color['b']);
                    } elseif ($moduleGradGd) {
                        $gx = min($x1, $actualQrSize - 1);
                        $gy = min($y1 - $qrOffsetY, $actualQrSize - 1);
                        if ($gy < 0) $gy = 0;
                        $gradPixel = imagecolorat($moduleGradGd, max(0, $gx), max(0, $gy));
                        $modColor = imagecolorallocate(
                            $canvasGd,
                            ($gradPixel >> 16) & 0xFF,
                            ($gradPixel >> 8) & 0xFF,
                            $gradPixel & 0xFF
                        );
                    } else {
                        $color = $moduleColor;
                        $modColor = imagecolorallocate($canvasGd, $color['r'], $color['g'], $color['b']);
                    }
                    
                    if ($isFinderArea) {
                        // Finder modules - always draw as squares for scannability
                        // Custom finder shapes are applied as overlay after
                        imagefilledrectangle($canvasGd, $x1, $y1, $x2, $y2, $modColor);
                    } else {
                        // Data modules - apply module shape
                        if ($moduleShape === 'square') {
                            imagefilledrectangle($canvasGd, $x1, $y1, $x2, $y2, $modColor);
                        } elseif ($moduleShape === 'dots' || $moduleShape === 'rounded') {
                            $radius = ($moduleShape === 'dots') ? $moduleSize * 0.45 : $moduleSize * 0.4;
                            imagefilledellipse($canvasGd, (int)$cx, (int)$cy, (int)($radius * 2), (int)($radius * 2), $modColor);
                        } else {
                            $this->drawShape($canvasGd, $moduleShape, $cx, $cy, $moduleSize * 0.95, $modColor);
                        }
                    }
                }
            }
        }
        
        // STEP 2: Apply custom finder shapes as overlay (only for non-square shapes)
        // These are drawn ON TOP of the square finders, maintaining the same dark/light pattern
        if ($finderShape !== 'square') {
            $finderPositions = [
                ['col' => $qzOffset, 'row' => $qzOffset],
                ['col' => $moduleCount - 7 - $qzOffset, 'row' => $qzOffset],
                ['col' => $qzOffset, 'row' => $moduleCount - 7 - $qzOffset],
            ];
            
            $darkColor = imagecolorallocate($canvasGd, $finderColor['r'], $finderColor['g'], $finderColor['b']);
            $solidLightColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
            
            foreach ($finderPositions as $pos) {
                $fx = $marginPx + ($pos['col'] * $moduleSize);
                $fy = $qrOffsetY + $marginPx + ($pos['row'] * $moduleSize);
                $finderPxSize = $moduleSize * 7;
                $fcx = $fx + ($finderPxSize / 2);
                $fcy = $fy + ($finderPxSize / 2);
                
                // Clear finder area first
                if ($bgGradGd) {
                    // Restore the background gradient under the finder area
                    imagecopy($canvasGd, $bgGradGd, $fx, $fy, $fx, $fy, $finderPxSize, $finderPxSize);
                } else {
                    imagefilledrectangle($canvasGd, $fx, $fy, $fx + $finderPxSize - 1, $fy + $finderPxSize - 1, $solidLightColor);
                }
                
                // When background is a gradient, use the gradient color at the center for "light" parts
                // (so finder holes don't become solid white/flat when gradients are enabled)
                $lightColor = $bgGradGd
                    ? $this->getGradientColorAt($bgGradGd, (int)$fcx, (int)$fcy, $actualQrSize, $qrOffsetY, $canvasGd)
                    : $solidLightColor;
                
                if ($finderShape === 'circle') {
                    imagefilledellipse($canvasGd, (int)$fcx, (int)$fcy, $finderPxSize, $finderPxSize, $darkColor);
                    imagefilledellipse($canvasGd, (int)$fcx, (int)$fcy, $moduleSize * 5, $moduleSize * 5, $lightColor);
                    imagefilledellipse($canvasGd, (int)$fcx, (int)$fcy, $moduleSize * 3, $moduleSize * 3, $darkColor);
                    
                } elseif ($finderShape === 'rounded') {
                    $radius = (int)($finderPxSize * 0.2);
                    $this->drawRoundedRect($canvasGd, $fx, $fy, $finderPxSize, $finderPxSize, $radius, $darkColor);
                    $this->drawRoundedRect($canvasGd, $fx + $moduleSize, $fy + $moduleSize, $moduleSize * 5, $moduleSize * 5, (int)($radius * 0.7), $lightColor);
                    $this->drawRoundedRect($canvasGd, $fx + $moduleSize * 2, $fy + $moduleSize * 2, $moduleSize * 3, $moduleSize * 3, (int)($radius * 0.5), $darkColor);
                    
                } elseif ($finderShape === 'leaf') {
                    $this->drawLeafShape($canvasGd, $fx, $fy, $finderPxSize, $finderPxSize, $darkColor, true);
                    $this->drawLeafShape($canvasGd, $fx + $moduleSize, $fy + $moduleSize, $moduleSize * 5, $moduleSize * 5, $lightColor, true);
                    $this->drawLeafShape($canvasGd, $fx + $moduleSize * 2, $fy + $moduleSize * 2, $moduleSize * 3, $moduleSize * 3, $darkColor, true);
                    
                } else {
                    // Custom shapes (star, diamond, heart, dots) - module by module
                    for ($fr = 0; $fr < 7; $fr++) {
                        for ($fc = 0; $fc < 7; $fc++) {
                            $mcx = $fx + ($fc * $moduleSize) + ($moduleSize / 2);
                            $mcy = $fy + ($fr * $moduleSize) + ($moduleSize / 2);
                            
                            $isOuterRing = ($fc == 0 || $fc == 6 || $fr == 0 || $fr == 6);
                            $isCenter = ($fc >= 2 && $fc <= 4 && $fr >= 2 && $fr <= 4);
                            
                            if ($isOuterRing || $isCenter) {
                                if ($finderShape === 'dots') {
                                    imagefilledellipse($canvasGd, (int)$mcx, (int)$mcy, (int)($moduleSize * 0.9), (int)($moduleSize * 0.9), $darkColor);
                                } else {
                                    $this->drawShape($canvasGd, $finderShape, $mcx, $mcy, $moduleSize * 0.95, $darkColor);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Convert QR canvas to Intervention Image

        ob_start();
        imagepng($canvasGd);
        $canvasData = ob_get_clean();
        $qrCanvas = $this->imageManager->read($canvasData);
        imagedestroy($canvasGd);
        if ($bgGradGd) imagedestroy($bgGradGd);
        if ($moduleGradGd) imagedestroy($moduleGradGd);

        // Add logo if provided (to QR area only)
        if (!empty($design['logo'])) {
            $qrCanvas = $this->addLogo($qrCanvas, $design, $actualQrSize, 0);
        }

        // Apply border to QR code only (before adding text)
        if (!empty($design['border'])) {
            $qrCanvas = $this->applyBorder(
                $qrCanvas,
                $design['border'],
                $actualQrSize,
                $actualQrSize,
                $design['background_color'] ?? '#FFFFFF',
                $design['background_gradient'] ?? null
            );
        }

        $borderRadius = !empty($design['border']['radius']) ? (int)$design['border']['radius'] : 0;
        $glowApplied = false;
        // Apply glow to the QR + border block so the glow sits behind them (not behind text/paper)
        if (!empty($design['glow'])) {
            $qrCanvas = $this->applyGlow(
                $qrCanvas,
                $design['glow'],
                $design['background_color'] ?? '#FFFFFF',
                $design['background_gradient'] ?? null,
                $borderRadius,
                $isPreview
            );
            $glowApplied = true;
        }

        $shadowApplied = false;
        // Apply shadow to the QR + border block so it hugs the QR edges
        if (!empty($design['shadow'])) {
            $qrCanvas = $this->applyShadow(
                $qrCanvas,
                $design['shadow'],
                $design['background_color'] ?? '#FFFFFF',
                $design['background_gradient'] ?? null,
                $borderRadius,
                $isPreview
            );
            $shadowApplied = true;
        }

        // Get bordered QR dimensions
        $qrCanvasGd = $qrCanvas->core()->native();
        $borderedQrWidth = imagesx($qrCanvasGd);
        $borderedQrHeight = imagesy($qrCanvasGd);

        $hasText = ($topTextHeight > 0 || $bottomTextHeight > 0);

        if ($hasText) {
            // Create final canvas with text areas outside the border
            $finalWidth = $borderedQrWidth;
            $finalHeight = $borderedQrHeight + $topTextHeight + $bottomTextHeight;
            
            // Create final canvas with background color for text areas
            $bgRgb = $this->hexToRgb($design['background_color'] ?: '#FFFFFF');
            $finalCanvas = $this->imageManager->create($finalWidth, (int)$finalHeight, sprintf('#%02x%02x%02x', $bgRgb['r'], $bgRgb['g'], $bgRgb['b']));
            
            // If background gradient, apply it to text areas too
            if (!empty($design['background_gradient'])) {
                $finalGradient = $this->createGradientImage($finalWidth, (int)$finalHeight, $design['background_gradient']);
                $finalCanvas = $finalGradient;
            }
            
            // Place bordered QR on final canvas
            $finalCanvasGd = $finalCanvas->core()->native();
            imagecopy($finalCanvasGd, $qrCanvasGd, 0, $topTextHeight, 0, 0, $borderedQrWidth, $borderedQrHeight);
            
            // Convert back
            ob_start();
            imagepng($finalCanvasGd);
            $finalData = ob_get_clean();
            $canvas = $this->imageManager->read($finalData);

            // Add text outside the border
            if (!empty($design['text_top'])) {
                $canvas = $this->addText($canvas, $design['text_top'], 'top', $design, $finalWidth, 0, $topTextHeight);
            }
            if (!empty($design['text_bottom'])) {
                $canvas = $this->addText($canvas, $design['text_bottom'], 'bottom', $design, $finalWidth, $topTextHeight + $borderedQrHeight, $bottomTextHeight);
            }
        } else {
            // No text - skip the extra canvas creation + PNG encode/decode cycle
            $canvas = $qrCanvas;
        }

        // Add a white paper margin around the final image so preview matches real-world print
        $canvas = $this->addPaperMargin($canvas, $isPreview ? 8 : 12);

        // If glow wasn't already applied earlier, apply it now (kept for safety)
        if (!empty($design['glow']) && !$glowApplied) {
            $canvas = $this->applyGlow(
                $canvas,
                $design['glow'],
                $design['background_color'] ?? '#FFFFFF',
                $design['background_gradient'] ?? null,
                $borderRadius,
                $isPreview
            );
        }

        // Apply shadow effect if it wasn't already applied
        if (!empty($design['shadow']) && !$shadowApplied) {
            $canvas = $this->applyShadow(
                $canvas,
                $design['shadow'],
                $design['background_color'] ?? '#FFFFFF',
                $design['background_gradient'] ?? null,
                $borderRadius,
                $isPreview
            );
        }

        $result = $canvas->toPng()->toDataUri();
        if ($isPreview && $previewCacheKey) {
            Cache::put($previewCacheKey, $result, now()->addSeconds(30));
        }
        return $result;
    }

    /**
     * Generate a QR code and return raw PNG binary (faster than base64 for previews)
     */
    public function generateBinary(string $data, array $design = []): string
    {
        $design['preview_mode'] = true;
        $dataUri = $this->generate($data, $design);
        // Strip data:image/png;base64, prefix and decode
        $base64 = substr($dataUri, strpos($dataUri, ',') + 1);
        return base64_decode($base64);
    }

    /**
     * Draw a single finder pattern at the given position
     */
    protected function drawFinderPattern($canvasGd, int $startCol, int $startRow, int $moduleCount, int $moduleSize, int $margin, int $offsetY, array $finderColor, array $bgColor, array $design, $bgGradGd, int $qrSize, string $finderShape): void
    {
        $darkColor = imagecolorallocate($canvasGd, $finderColor['r'], $finderColor['g'], $finderColor['b']);
        $lightColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        
        // Calculate pixel bounds for 7x7 area
        $fx = $margin + ($startCol * $moduleSize);
        $fy = $offsetY + $margin + ($startRow * $moduleSize);
        $finderPxSize = $moduleSize * 7;
        $cx = $fx + ($finderPxSize / 2);
        $cy = $fy + ($finderPxSize / 2);
        
        // Fill entire 7x7 area with background first
        if ($bgGradGd) {
            for ($r = 0; $r < 7; $r++) {
                for ($c = 0; $c < 7; $c++) {
                    $px1 = $fx + ($c * $moduleSize);
                    $py1 = $fy + ($r * $moduleSize);
                    $gx = min($px1, $qrSize - 1);
                    $gy = min($py1 - $offsetY, $qrSize - 1);
                    if ($gy < 0) $gy = 0;
                    $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
                    $bgModColor = imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
                    imagefilledrectangle($canvasGd, $px1, $py1, $px1 + $moduleSize - 1, $py1 + $moduleSize - 1, $bgModColor);
                }
            }
        } else {
            imagefilledrectangle($canvasGd, $fx, $fy, $fx + $finderPxSize - 1, $fy + $finderPxSize - 1, $lightColor);
        }
        
        // Draw finder pattern based on shape
        switch ($finderShape) {
            case 'circle':
                // Outer circle (7 modules)
                imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $finderPxSize, $finderPxSize, $darkColor);
                // Middle circle (5 modules) - light
                $middleSize = $moduleSize * 5;
                $middleLightColor = $bgGradGd ? $this->getGradientColorAt($bgGradGd, $fx + $moduleSize * 3, $fy + $moduleSize * 3, $qrSize, $offsetY, $canvasGd) : $lightColor;
                imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $middleSize, $middleSize, $middleLightColor);
                // Inner circle (3 modules) - dark
                $innerSize = $moduleSize * 3;
                imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $innerSize, $innerSize, $darkColor);
                break;
                
            case 'rounded':
                $radius = (int)($finderPxSize * 0.15);
                // Outer rounded rect
                $this->drawRoundedRect($canvasGd, $fx, $fy, $finderPxSize, $finderPxSize, $radius, $darkColor);
                // Middle rounded rect - light
                $middleOffset = $moduleSize;
                $middleSize = $moduleSize * 5;
                $middleLightColor = $bgGradGd ? $this->getGradientColorAt($bgGradGd, $fx + $moduleSize * 3, $fy + $moduleSize * 3, $qrSize, $offsetY, $canvasGd) : $lightColor;
                $this->drawRoundedRect($canvasGd, $fx + $middleOffset, $fy + $middleOffset, $middleSize, $middleSize, (int)($radius * 0.7), $middleLightColor);
                // Inner rounded rect - dark
                $innerOffset = $moduleSize * 2;
                $innerSize = $moduleSize * 3;
                $this->drawRoundedRect($canvasGd, $fx + $innerOffset, $fy + $innerOffset, $innerSize, $innerSize, (int)($radius * 0.5), $darkColor);
                break;
                
            case 'square':
            default:
                // Outer square (7x7)
                imagefilledrectangle($canvasGd, $fx, $fy, $fx + $finderPxSize - 1, $fy + $finderPxSize - 1, $darkColor);
                // Middle square (5x5) - light
                $middleOffset = $moduleSize;
                $middleSize = $moduleSize * 5;
                if ($bgGradGd) {
                    for ($r = 0; $r < 5; $r++) {
                        for ($c = 0; $c < 5; $c++) {
                            $px1 = $fx + $middleOffset + ($c * $moduleSize);
                            $py1 = $fy + $middleOffset + ($r * $moduleSize);
                            $gx = min($px1, $qrSize - 1);
                            $gy = min($py1 - $offsetY, $qrSize - 1);
                            if ($gy < 0) $gy = 0;
                            $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
                            $bgModColor = imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
                            imagefilledrectangle($canvasGd, $px1, $py1, $px1 + $moduleSize - 1, $py1 + $moduleSize - 1, $bgModColor);
                        }
                    }
                } else {
                    imagefilledrectangle($canvasGd, $fx + $middleOffset, $fy + $middleOffset, $fx + $middleOffset + $middleSize - 1, $fy + $middleOffset + $middleSize - 1, $lightColor);
                }
                // Inner square (3x3) - dark
                $innerOffset = $moduleSize * 2;
                $innerSize = $moduleSize * 3;
                imagefilledrectangle($canvasGd, $fx + $innerOffset, $fy + $innerOffset, $fx + $innerOffset + $innerSize - 1, $fy + $innerOffset + $innerSize - 1, $darkColor);
                break;
        }
    }
    
    /**
     * Get gradient color at specific position
     */
    protected function getGradientColorAt($bgGradGd, int $x, int $y, int $qrSize, int $offsetY, $canvasGd)
    {
        $gx = min($x, $qrSize - 1);
        $gy = min($y - $offsetY, $qrSize - 1);
        if ($gy < 0) $gy = 0;
        $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
        return imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
    }

    /**
     * Draw finder patterns (the three corner squares) - DEPRECATED, using drawFinderPattern instead
     */
    protected function drawFinderPatterns($canvasGd, $matrix, int $moduleCount, int $moduleSize, int $margin, int $offsetY, array $finderColor, array $bgColor, array $design, $bgGradGd, int $qrSize): void
    {
        $darkColor = imagecolorallocate($canvasGd, $finderColor['r'], $finderColor['g'], $finderColor['b']);
        $lightColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        
        $finderShape = $design['finder_shape'] ?? 'square';
        
        // Finder positions (top-left corner of each 7x7 finder)
        $finderPositions = [
            ['col' => 0, 'row' => 0],
            ['col' => $moduleCount - 7, 'row' => 0],
            ['col' => 0, 'row' => $moduleCount - 7],
        ];
        
        foreach ($finderPositions as $pos) {
            $startCol = $pos['col'];
            $startRow = $pos['row'];
            
            // Calculate pixel bounds for 7x7 area
            $fx = $margin + ($startCol * $moduleSize);
            $fy = $offsetY + $margin + ($startRow * $moduleSize);
            $finderPxSize = $moduleSize * 7;
            
            // First, fill the entire 7x7 area with background
            if ($bgGradGd) {
                // Sample and fill with gradient
                for ($r = 0; $r < 7; $r++) {
                    for ($c = 0; $c < 7; $c++) {
                        $px1 = $fx + ($c * $moduleSize);
                        $py1 = $fy + ($r * $moduleSize);
                        $gx = min($px1, $qrSize - 1);
                        $gy = min($py1 - $offsetY, $qrSize - 1);
                        if ($gy < 0) $gy = 0;
                        $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
                        $bgModColor = imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
                        imagefilledrectangle($canvasGd, $px1, $py1, $px1 + $moduleSize - 1, $py1 + $moduleSize - 1, $bgModColor);
                    }
                }
            } else {
                imagefilledrectangle($canvasGd, $fx, $fy, $fx + $finderPxSize - 1, $fy + $finderPxSize - 1, $lightColor);
            }
            
            // Now draw the finder pattern based on shape
            $cx = $fx + ($finderPxSize / 2);
            $cy = $fy + ($finderPxSize / 2);
            
            // Get middle background color for gradient
            $middleLightColor = $lightColor;
            if ($bgGradGd) {
                $gx = min($fx + $moduleSize * 3, $qrSize - 1);
                $gy = min($fy + $moduleSize * 3 - $offsetY, $qrSize - 1);
                if ($gy < 0) $gy = 0;
                $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
                $middleLightColor = imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
            }
            
            switch ($finderShape) {
                case 'circle':
                    // Outer circle (7 modules)
                    imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $finderPxSize, $finderPxSize, $darkColor);
                    // Middle circle (5 modules) - light
                    $middleSize = $moduleSize * 5;
                    imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $middleSize, $middleSize, $middleLightColor);
                    // Inner circle (3 modules) - dark
                    $innerSize = $moduleSize * 3;
                    imagefilledellipse($canvasGd, (int)$cx, (int)$cy, $innerSize, $innerSize, $darkColor);
                    break;
                    
                case 'rounded':
                    $radius = (int)($finderPxSize * 0.15);
                    // Outer rounded rect
                    $this->drawRoundedRect($canvasGd, $fx, $fy, $finderPxSize, $finderPxSize, $radius, $darkColor);
                    // Middle rounded rect - light
                    $middleOffset = $moduleSize;
                    $middleSize = $moduleSize * 5;
                    $this->drawRoundedRect($canvasGd, $fx + $middleOffset, $fy + $middleOffset, $middleSize, $middleSize, (int)($radius * 0.7), $middleLightColor);
                    // Inner rounded rect - dark
                    $innerOffset = $moduleSize * 2;
                    $innerSize = $moduleSize * 3;
                    $this->drawRoundedRect($canvasGd, $fx + $innerOffset, $fy + $innerOffset, $innerSize, $innerSize, (int)($radius * 0.5), $darkColor);
                    break;
                    
                case 'leaf':
                    // Outer leaf
                    $this->drawLeafShape($canvasGd, $fx, $fy, $finderPxSize, $finderPxSize, $darkColor, true);
                    // Middle leaf - light
                    $middleOffset = $moduleSize;
                    $middleSize = $moduleSize * 5;
                    $this->drawLeafShape($canvasGd, $fx + $middleOffset, $fy + $middleOffset, $middleSize, $middleSize, $middleLightColor, true);
                    // Inner leaf - dark
                    $innerOffset = $moduleSize * 2;
                    $innerSize = $moduleSize * 3;
                    $this->drawLeafShape($canvasGd, $fx + $innerOffset, $fy + $innerOffset, $innerSize, $innerSize, $darkColor, true);
                    break;
                    
                case 'square':
                default:
                    // Outer square (7x7)
                    imagefilledrectangle($canvasGd, $fx, $fy, $fx + $finderPxSize - 1, $fy + $finderPxSize - 1, $darkColor);
                    // Middle square (5x5) - light
                    $middleOffset = $moduleSize;
                    $middleSize = $moduleSize * 5;
                    if ($bgGradGd) {
                        // Fill with gradient module by module
                        for ($r = 0; $r < 5; $r++) {
                            for ($c = 0; $c < 5; $c++) {
                                $px1 = $fx + $middleOffset + ($c * $moduleSize);
                                $py1 = $fy + $middleOffset + ($r * $moduleSize);
                                $gx = min($px1, $qrSize - 1);
                                $gy = min($py1 - $offsetY, $qrSize - 1);
                                if ($gy < 0) $gy = 0;
                                $gradPixel = imagecolorat($bgGradGd, $gx, $gy);
                                $bgModColor = imagecolorallocate($canvasGd, ($gradPixel >> 16) & 0xFF, ($gradPixel >> 8) & 0xFF, $gradPixel & 0xFF);
                                imagefilledrectangle($canvasGd, $px1, $py1, $px1 + $moduleSize - 1, $py1 + $moduleSize - 1, $bgModColor);
                            }
                        }
                    } else {
                        imagefilledrectangle($canvasGd, $fx + $middleOffset, $fy + $middleOffset, $fx + $middleOffset + $middleSize - 1, $fy + $middleOffset + $middleSize - 1, $lightColor);
                    }
                    // Inner square (3x3) - dark
                    $innerOffset = $moduleSize * 2;
                    $innerSize = $moduleSize * 3;
                    imagefilledrectangle($canvasGd, $fx + $innerOffset, $fy + $innerOffset, $fx + $innerOffset + $innerSize - 1, $fy + $innerOffset + $innerSize - 1, $darkColor);
                    break;
            }
        }
    }

    /**
     * Calculate exact pixel position for a module
     * Returns array with x1, y1, x2, y2, cx, cy
     * All values are exact integers when moduleSize and margin are integers
     */
    protected function getModulePixelBounds(int $col, int $row, $moduleSize, int $margin, int $offsetY): array
    {
        // With integer scale, these are exact pixel positions
        $x1 = $margin + ($col * $moduleSize);
        $y1 = $offsetY + $margin + ($row * $moduleSize);
        $x2 = $margin + (($col + 1) * $moduleSize);
        $y2 = $offsetY + $margin + (($row + 1) * $moduleSize);
        
        return [
            'x1' => (int)$x1,
            'y1' => (int)$y1,
            'x2' => (int)$x2,
            'y2' => (int)$y2,
            'cx' => $x1 + ($moduleSize / 2),
            'cy' => $y1 + ($moduleSize / 2),
            'width' => (int)$moduleSize,
            'height' => (int)$moduleSize,
        ];
    }

    /**
     * Create gradient image
     */
    protected function createGradientImage(int $width, int $height, array $gradient)
    {
        $cacheKey = sha1(json_encode($gradient) . "|{$width}x{$height}");
        if (isset($this->gradientCache[$cacheKey])) {
            return $this->imageManager->read($this->gradientCache[$cacheKey]);
        }

        $image = imagecreatetruecolor($width, $height);
        $colors = $this->parseGradientColors($gradient);
        $type = $gradient['type'] ?? 'linear';
        $angle = $gradient['angle'] ?? 135;

        if ($type === 'radial') {
            $this->drawRadialGradientRect($image, $width, $height, $colors);
        } else {
            $this->drawLinearGradientRect($image, $width, $height, $colors, $angle);
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        // Cache raw PNG so repeated gradient requests reuse the same buffer
        $this->gradientCache[$cacheKey] = $imageData;

        return $this->imageManager->read($imageData);
    }

    /**
     * Apply background gradient to light modules using matrix
     */
    protected function applyBackgroundGradient($canvas, $matrix, int $moduleCount, float $moduleSize, array $gradient, int $size, int $margin, int $offsetY)
    {
        $gradientImage = $this->createGradientImage($size, $size, $gradient);
        $gradGd = $gradientImage->core()->native();
        $canvasGd = $canvas->core()->native();

        // Iterate through matrix to find light modules
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                // Skip finder pattern areas - they're handled separately
                if (($col < 7 && $row < 7) || 
                    ($col >= $moduleCount - 7 && $row < 7) || 
                    ($col < 7 && $row >= $moduleCount - 7)) {
                    continue;
                }
                
                $moduleValue = $matrix->get($col, $row);
                
                // Check if light module (not dark)
                if (($moduleValue & 0x800) === 0) {
                    $bounds = $this->getModulePixelBounds($col, $row, $moduleSize, $margin, $offsetY);
                    
                    // Sample gradient at module center (relative to QR area)
                    $gx = min((int)round($margin + ($col * $moduleSize) + ($moduleSize / 2)), $size - 1);
                    $gy = min((int)round($margin + ($row * $moduleSize) + ($moduleSize / 2)), $size - 1);
                    $gradColor = imagecolorat($gradGd, $gx, $gy);
                    $gr = ($gradColor >> 16) & 0xFF;
                    $gg = ($gradColor >> 8) & 0xFF;
                    $gb = $gradColor & 0xFF;
                    
                    $newColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    
                    // Fill module area with gradient color
                    imagefilledrectangle($canvasGd, $bounds['x1'], $bounds['y1'], $bounds['x2'] - 1, $bounds['y2'] - 1, $newColor);
                }
            }
        }

        ob_start();
        imagepng($canvasGd);
        $resultData = ob_get_clean();
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Apply module gradient using matrix for exact positions
     */
    protected function applyModuleGradient($canvas, $matrix, int $moduleCount, float $moduleSize, array $gradient, int $size, int $margin, int $offsetY)
    {
        $gradientImage = $this->createGradientImage($size, $size, $gradient);
        $gradGd = $gradientImage->core()->native();
        $canvasGd = $canvas->core()->native();

        // Iterate through matrix to find dark modules
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                // Skip finder pattern areas
                if (($col < 7 && $row < 7) || 
                    ($col >= $moduleCount - 7 && $row < 7) || 
                    ($col < 7 && $row >= $moduleCount - 7)) {
                    continue;
                }
                
                $moduleValue = $matrix->get($col, $row);
                
                // Check if dark module
                if (($moduleValue & 0x800) !== 0) {
                    // Calculate module bounds (accounting for canvas offset) - use exact pixel positions
                    $x1 = (int)round($margin + ($col * $moduleSize));
                    $y1 = (int)round($offsetY + $margin + ($row * $moduleSize));
                    $x2 = (int)round($margin + (($col + 1) * $moduleSize));
                    $y2 = (int)round($offsetY + $margin + (($row + 1) * $moduleSize));
                    
                    // Sample gradient at module center (relative to QR area, not canvas)
                    $gx = min((int)round($margin + ($col * $moduleSize) + ($moduleSize / 2)), $size - 1);
                    $gy = min((int)round($margin + ($row * $moduleSize) + ($moduleSize / 2)), $size - 1);
                    $gradColor = imagecolorat($gradGd, $gx, $gy);
                    $gr = ($gradColor >> 16) & 0xFF;
                    $gg = ($gradColor >> 8) & 0xFF;
                    $gb = $gradColor & 0xFF;
                    
                    $newColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    
                    // Fill module area with gradient color
                    imagefilledrectangle($canvasGd, $x1, $y1, $x2 - 1, $y2 - 1, $newColor);
                }
            }
        }

        ob_start();
        imagepng($canvasGd);
        $resultData = ob_get_clean();
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Apply custom module shapes using matrix for exact positions
     */
    protected function applyCustomModuleShape($canvas, $matrix, int $moduleCount, float $moduleSize, string $shape, array $design, int $size, int $margin, int $offsetY)
    {
        $moduleColor = $this->hexToRgb($design['module_color'] ?: '#000000');
        $moduleGradient = !empty($design['module_gradient']) ? $design['module_gradient'] : null;
        
        $canvasGd = $canvas->core()->native();
        $modColor = imagecolorallocate($canvasGd, $moduleColor['r'], $moduleColor['g'], $moduleColor['b']);
        
        // Create gradient image if needed
        $gradientGd = null;
        if ($moduleGradient) {
            $gradientImage = $this->createGradientImage($size, $size, $moduleGradient);
            $gradientGd = $gradientImage->core()->native();
        }
        
        // Get background color for clearing modules
        $hasBgGradient = !empty($design['background_gradient']);
        if ($hasBgGradient) {
            $bgGradientImage = $this->createGradientImage($size, $size, $design['background_gradient']);
            $bgGradGd = $bgGradientImage->core()->native();
        } else {
            $bgRgb = $this->hexToRgb($design['background_color'] ?? '#FFFFFF');
            $bgColor = imagecolorallocate($canvasGd, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
        }
        
        // Iterate through matrix to find dark modules
        for ($row = 0; $row < $moduleCount; $row++) {
            for ($col = 0; $col < $moduleCount; $col++) {
                // Skip finder pattern areas (they're handled separately)
                if (($col < 7 && $row < 7) || 
                    ($col >= $moduleCount - 7 && $row < 7) || 
                    ($col < 7 && $row >= $moduleCount - 7)) {
                    continue;
                }
                
                $moduleValue = $matrix->get($col, $row);
                
                // Check if dark module
                if (($moduleValue & 0x800) !== 0) {
                    $bounds = $this->getModulePixelBounds($col, $row, $moduleSize, $margin, $offsetY);
                    
                    // Clear module area first with background
                    if ($hasBgGradient) {
                        $gx = min((int)round($margin + ($col * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($row * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($bgGradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $clearColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    } else {
                        $clearColor = $bgColor;
                    }
                    imagefilledrectangle($canvasGd, $bounds['x1'], $bounds['y1'], $bounds['x2'] - 1, $bounds['y2'] - 1, $clearColor);
                    
                    // Shape size should fill most of module (95% to ensure coverage)
                    $shapeSize = min($bounds['width'], $bounds['height']) * 0.95;
                    
                    // Get color (from gradient if available)
                    if ($gradientGd) {
                        // Sample gradient at module center (relative to QR area)
                        $gx = min((int)round($margin + ($col * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($row * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradientGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $modColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    }
                    
                    $this->drawShape($canvasGd, $shape, $bounds['cx'], $bounds['cy'], $shapeSize, $modColor);
                }
            }
        }

        ob_start();
        imagepng($canvasGd);
        $resultData = ob_get_clean();
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Draw a shape with exact pixel coordinates
     */
    protected function drawShape($gdImage, string $shape, float $cx, float $cy, float $size, $color)
    {
        // Convert to exact integers
        $cx = (int)round($cx);
        $cy = (int)round($cy);
        $halfSize = (int)round($size / 2);
        
        switch ($shape) {
            case 'diamond':
                $points = [
                    $cx, $cy - $halfSize,
                    $cx + $halfSize, $cy,
                    $cx, $cy + $halfSize,
                    $cx - $halfSize, $cy,
                ];
                imagefilledpolygon($gdImage, $points, 4, $color);
                break;
                
            case 'star':
                $points = [];
                for ($i = 0; $i < 10; $i++) {
                    $angle = deg2rad(-90 + $i * 36);
                    $radius = ($i % 2 === 0) ? $halfSize : (int)round($halfSize * 0.4);
                    $points[] = (int)round($cx + cos($angle) * $radius);
                    $points[] = (int)round($cy + sin($angle) * $radius);
                }
                imagefilledpolygon($gdImage, $points, 10, $color);
                break;
                
            case 'heart':
                $heartSize = (int)round($halfSize * 0.9);
                $leftX = (int)round($cx - $heartSize * 0.3);
                $rightX = (int)round($cx + $heartSize * 0.3);
                $topY = (int)round($cy - $heartSize * 0.2);
                
                // Draw two circles for top of heart
                imagefilledellipse($gdImage, $leftX, $topY, $heartSize, $heartSize, $color);
                imagefilledellipse($gdImage, $rightX, $topY, $heartSize, $heartSize, $color);
                
                // Draw triangle for bottom of heart
                $points = [
                    $cx - $halfSize, (int)round($cy - $heartSize * 0.1),
                    $cx + $halfSize, (int)round($cy - $heartSize * 0.1),
                    $cx, $cy + $halfSize,
                ];
                imagefilledpolygon($gdImage, $points, 3, $color);
                break;
        }
    }

    /**
     * Apply square finder patterns - ensure they're solid squares, not dots
     */
    protected function applySquareFinderPatterns($canvas, $matrix, int $moduleCount, float $moduleSize, array $finderColor, array $bgColor, array $design, int $size, int $margin, int $offsetY)
    {
        $canvasGd = $canvas->core()->native();
        $darkColor = imagecolorallocate($canvasGd, $finderColor['r'], $finderColor['g'], $finderColor['b']);
        
        // Get background color from gradient if exists, otherwise use solid
        $hasBgGradient = !empty($design['background_gradient']);
        if ($hasBgGradient) {
            $gradientImage = $this->createGradientImage($size, $size, $design['background_gradient']);
            $gradGd = $gradientImage->core()->native();
        } else {
            $lightColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        }

        // Finder positions from matrix (7x7 modules in corners)
        $finderPositions = [
            ['col' => 0, 'row' => 0],                                    // Top-left
            ['col' => $moduleCount - 7, 'row' => 0],                    // Top-right
            ['col' => 0, 'row' => $moduleCount - 7],                    // Bottom-left
        ];

        foreach ($finderPositions as $pos) {
            $startCol = $pos['col'];
            $startRow = $pos['row'];
            
            // Calculate exact pixel bounds for the 7x7 finder area using helper
            $startBounds = $this->getModulePixelBounds($startCol, $startRow, $moduleSize, $margin, $offsetY);
            $endBounds = $this->getModulePixelBounds($startCol + 7, $startRow + 7, $moduleSize, $margin, $offsetY);
            $fx = $startBounds['x1'];
            $fy = $startBounds['y1'];
            $fxEnd = $endBounds['x1'];
            $fyEnd = $endBounds['y1'];
            
            // Draw finder pattern: outer square (7x7), middle square (5x5 light), inner square (3x3 dark)
            // Outer square - dark
            imagefilledrectangle($canvasGd, $fx, $fy, $fxEnd - 1, $fyEnd - 1, $darkColor);
            
            // Middle square - light (5x5 modules, starting at +1)
            $middleStartCol = $startCol + 1;
            $middleStartRow = $startRow + 1;
            $middleStartBounds = $this->getModulePixelBounds($middleStartCol, $middleStartRow, $moduleSize, $margin, $offsetY);
            $middleEndBounds = $this->getModulePixelBounds($middleStartCol + 5, $middleStartRow + 5, $moduleSize, $margin, $offsetY);
            $middleX1 = $middleStartBounds['x1'];
            $middleY1 = $middleStartBounds['y1'];
            $middleX2 = $middleEndBounds['x1'];
            $middleY2 = $middleEndBounds['y1'];
            
            if ($hasBgGradient) {
                // Sample gradient for middle square
                for ($r = 0; $r < 5; $r++) {
                    for ($c = 0; $c < 5; $c++) {
                        $modCol = $middleStartCol + $c;
                        $modRow = $middleStartRow + $r;
                        $modBounds = $this->getModulePixelBounds($modCol, $modRow, $moduleSize, $margin, $offsetY);
                        
                        $gx = min((int)round($margin + ($modCol * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($modRow * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $lightColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                        imagefilledrectangle($canvasGd, $modBounds['x1'], $modBounds['y1'], $modBounds['x2'] - 1, $modBounds['y2'] - 1, $lightColor);
                    }
                }
            } else {
                imagefilledrectangle($canvasGd, $middleX1, $middleY1, $middleX2 - 1, $middleY2 - 1, $lightColor);
            }
            
            // Inner square - dark (3x3 modules, starting at +2)
            $innerStartCol = $startCol + 2;
            $innerStartRow = $startRow + 2;
            $innerStartBounds = $this->getModulePixelBounds($innerStartCol, $innerStartRow, $moduleSize, $margin, $offsetY);
            $innerEndBounds = $this->getModulePixelBounds($innerStartCol + 3, $innerStartRow + 3, $moduleSize, $margin, $offsetY);
            $innerX1 = $innerStartBounds['x1'];
            $innerY1 = $innerStartBounds['y1'];
            $innerX2 = $innerEndBounds['x1'];
            $innerY2 = $innerEndBounds['y1'];
            imagefilledrectangle($canvasGd, $innerX1, $innerY1, $innerX2 - 1, $innerY2 - 1, $darkColor);
        }

        ob_start();
        imagepng($canvasGd);
        $resultData = ob_get_clean();
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Apply custom finder shapes using matrix for exact positions
     */
    protected function applyFinderShape($canvas, $matrix, int $moduleCount, float $moduleSize, string $shape, array $finderColor, array $bgColor, array $design, int $size, int $margin, int $offsetY)
    {
        $canvasGd = $canvas->core()->native();
        $darkColor = imagecolorallocate($canvasGd, $finderColor['r'], $finderColor['g'], $finderColor['b']);
        
        // Get background color from gradient if exists, otherwise use solid
        $hasBgGradient = !empty($design['background_gradient']);
        if ($hasBgGradient) {
            $gradientImage = $this->createGradientImage($size, $size, $design['background_gradient']);
            $gradGd = $gradientImage->core()->native();
        } else {
            $lightColor = imagecolorallocate($canvasGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        }

        // Finder positions from matrix (7x7 modules in corners)
        $finderPositions = [
            ['col' => 0, 'row' => 0],                                    // Top-left
            ['col' => $moduleCount - 7, 'row' => 0],                    // Top-right
            ['col' => 0, 'row' => $moduleCount - 7],                    // Bottom-left
        ];

        foreach ($finderPositions as $pos) {
            // Calculate exact pixel positions using helper function
            $startCol = $pos['col'];
            $startRow = $pos['row'];
            
            // Calculate exact pixel bounds for the 7x7 finder area
            $startBounds = $this->getModulePixelBounds($startCol, $startRow, $moduleSize, $margin, $offsetY);
            $endBounds = $this->getModulePixelBounds($startCol + 7, $startRow + 7, $moduleSize, $margin, $offsetY);
            $fx = $startBounds['x1'];
            $fy = $startBounds['y1'];
            $fxEnd = $endBounds['x1'];
            $fyEnd = $endBounds['y1'];
            $finderSize = $fxEnd - $fx;
            $finderHeight = $fyEnd - $fy;
            
            // Center for shapes (must be exact)
            $cx = ($fx + $fxEnd) / 2;
            $cy = ($fy + $fyEnd) / 2;

            // Clear finder area first (7x7 modules) - completely fill the area to cover old patterns
            // Use background gradient if available, otherwise solid color
            if ($hasBgGradient) {
                // Sample gradient for each module in finder area using exact pixel calculations
                for ($r = 0; $r < 7; $r++) {
                    for ($c = 0; $c < 7; $c++) {
                        // Calculate exact module bounds - must match the drawing positions exactly
                        $modCol = $startCol + $c;
                        $modRow = $startRow + $r;
                        $x1 = (int)round($margin + ($modCol * $moduleSize));
                        $y1 = (int)round($offsetY + $margin + ($modRow * $moduleSize));
                        $x2 = (int)round($margin + (($modCol + 1) * $moduleSize));
                        $y2 = (int)round($offsetY + $margin + (($modRow + 1) * $moduleSize));
                        
                        // Sample gradient at module center
                        $gx = min((int)round($margin + ($modCol * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($modRow * $moduleSize) + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $lightColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                        
                        // Fill exact module area - ensure complete coverage
                        imagefilledrectangle($canvasGd, $x1, $y1, $x2 - 1, $y2 - 1, $lightColor);
                    }
                }
            } else {
                // Fill entire 7x7 area with solid background color
                imagefilledrectangle($canvasGd, $fx, $fy, $fxEnd - 1, $fyEnd - 1, $lightColor);
            }

            // Draw finder pattern based on shape - using exact module-aligned positions
            // Ensure shapes match the exact size and position of the underlying QR modules
            switch ($shape) {
                case 'circle':
                    // Outer circle: match exact 7x7 module area dimensions
                    // Use exact width/height to match the square area perfectly
                    imagefilledellipse($canvasGd, $cx, $cy, $finderSize, $finderHeight, $darkColor);
                    
                    // Middle ring: 5x5 modules, centered - match exact dimensions
                    $middleStartCol = $startCol + 1;
                    $middleStartRow = $startRow + 1;
                    $middleX1 = (int)round($margin + ($middleStartCol * $moduleSize));
                    $middleY1 = (int)round($offsetY + $margin + ($middleStartRow * $moduleSize));
                    $middleX2 = (int)round($margin + (($middleStartCol + 5) * $moduleSize));
                    $middleY2 = (int)round($offsetY + $margin + (($middleStartRow + 5) * $moduleSize));
                    $middleSize = $middleX2 - $middleX1;
                    $middleHeight = $middleY2 - $middleY1;
                    $middleCx = (int)round($middleX1 + ($middleSize / 2));
                    $middleCy = (int)round($middleY1 + ($middleHeight / 2));
                    
                    if ($hasBgGradient) {
                        $gx = min((int)round($margin + ($startCol + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($startRow + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $middleColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    } else {
                        $middleColor = $lightColor;
                    }
                    imagefilledellipse($canvasGd, $middleCx, $middleCy, $middleSize, $middleHeight, $middleColor);
                    
                    // Inner circle: 3x3 modules, centered - match exact dimensions
                    $innerStartCol = $startCol + 2;
                    $innerStartRow = $startRow + 2;
                    $innerStartBounds = $this->getModulePixelBounds($innerStartCol, $innerStartRow, $moduleSize, $margin, $offsetY);
                    $innerEndBounds = $this->getModulePixelBounds($innerStartCol + 3, $innerStartRow + 3, $moduleSize, $margin, $offsetY);
                    $innerX1 = $innerStartBounds['x1'];
                    $innerY1 = $innerStartBounds['y1'];
                    $innerX2 = $innerEndBounds['x1'];
                    $innerY2 = $innerEndBounds['y1'];
                    $innerSize = $innerX2 - $innerX1;
                    $innerHeight = $innerY2 - $innerY1;
                    $innerCx = ($innerX1 + $innerX2) / 2;
                    $innerCy = ($innerY1 + $innerY2) / 2;
                    imagefilledellipse($canvasGd, $innerCx, $innerCy, $innerSize, $innerHeight, $darkColor);
                    break;
                    
                case 'rounded':
                    $radius = (int)round($finderSize * 0.15);
                    $this->drawRoundedRect($canvasGd, $fx, $fy, $finderSize, $finderHeight, $radius, $darkColor);
                    
                    // Middle: 5x5 modules
                    $middleStartCol = $startCol + 1;
                    $middleStartRow = $startRow + 1;
                    $middleX1 = (int)round($margin + ($middleStartCol * $moduleSize));
                    $middleY1 = (int)round($offsetY + $margin + ($middleStartRow * $moduleSize));
                    $middleX2 = (int)round($margin + (($middleStartCol + 5) * $moduleSize));
                    $middleY2 = (int)round($offsetY + $margin + (($middleStartRow + 5) * $moduleSize));
                    $middleSize = $middleX2 - $middleX1;
                    $middleHeight = $middleY2 - $middleY1;
                    
                    if ($hasBgGradient) {
                        $gx = min((int)round($margin + ($startCol + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($startRow + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $middleColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    } else {
                        $middleColor = $lightColor;
                    }
                    $this->drawRoundedRect($canvasGd, $middleX1, $middleY1, $middleSize, $middleHeight, (int)round($radius * 0.7), $middleColor);
                    
                    // Inner: 3x3 modules
                    $innerStartCol = $startCol + 2;
                    $innerStartRow = $startRow + 2;
                    $innerX1 = (int)round($margin + ($innerStartCol * $moduleSize));
                    $innerY1 = (int)round($offsetY + $margin + ($innerStartRow * $moduleSize));
                    $innerX2 = (int)round($margin + (($innerStartCol + 3) * $moduleSize));
                    $innerY2 = (int)round($offsetY + $margin + (($innerStartRow + 3) * $moduleSize));
                    $innerSize = $innerX2 - $innerX1;
                    $innerHeight = $innerY2 - $innerY1;
                    $this->drawRoundedRect($canvasGd, $innerX1, $innerY1, $innerSize, $innerHeight, (int)round($radius * 0.5), $darkColor);
                    break;
                    
                case 'leaf':
                    $this->drawLeafShape($canvasGd, $fx, $fy, $finderSize, $finderHeight, $darkColor, true);
                    
                    // Middle: 5x5 modules
                    $middleStartCol = $startCol + 1;
                    $middleStartRow = $startRow + 1;
                    $middleX1 = (int)round($margin + ($middleStartCol * $moduleSize));
                    $middleY1 = (int)round($offsetY + $margin + ($middleStartRow * $moduleSize));
                    $middleX2 = (int)round($margin + (($middleStartCol + 5) * $moduleSize));
                    $middleY2 = (int)round($offsetY + $margin + (($middleStartRow + 5) * $moduleSize));
                    $middleSize = $middleX2 - $middleX1;
                    $middleHeight = $middleY2 - $middleY1;
                    
                    if ($hasBgGradient) {
                        $gx = min((int)round($margin + ($startCol + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gy = min((int)round($margin + ($startRow + 3) * $moduleSize + ($moduleSize / 2)), $size - 1);
                        $gradColor = imagecolorat($gradGd, $gx, $gy);
                        $gr = ($gradColor >> 16) & 0xFF;
                        $gg = ($gradColor >> 8) & 0xFF;
                        $gb = $gradColor & 0xFF;
                        $middleColor = imagecolorallocate($canvasGd, $gr, $gg, $gb);
                    } else {
                        $middleColor = $lightColor;
                    }
                    $this->drawLeafShape($canvasGd, $middleX1, $middleY1, $middleSize, $middleHeight, $middleColor, true);
                    
                    // Inner: 3x3 modules
                    $innerStartCol = $startCol + 2;
                    $innerStartRow = $startRow + 2;
                    $innerX1 = (int)round($margin + ($innerStartCol * $moduleSize));
                    $innerY1 = (int)round($offsetY + $margin + ($innerStartRow * $moduleSize));
                    $innerX2 = (int)round($margin + (($innerStartCol + 3) * $moduleSize));
                    $innerY2 = (int)round($offsetY + $margin + (($innerStartRow + 3) * $moduleSize));
                    $innerSize = $innerX2 - $innerX1;
                    $innerHeight = $innerY2 - $innerY1;
                    $this->drawLeafShape($canvasGd, $innerX1, $innerY1, $innerSize, $innerHeight, $darkColor, true);
                    break;
            }
        }

        ob_start();
        imagepng($canvasGd);
        $resultData = ob_get_clean();
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Draw rounded rectangle
     */
    protected function drawRoundedRect($gdImage, float $x, float $y, float $width, float $height, float $radius, $color): void
    {
        $x = (int)$x;
        $y = (int)$y;
        $width = (int)$width;
        $height = (int)$height;
        $radius = min((int)$radius, min($width, $height) / 2);
        
        // Use width-1 and height-1 for correct pixel bounds (GD uses inclusive coordinates)
        $x2 = $x + $width - 1;
        $y2 = $y + $height - 1;

        // Main body rectangles (avoiding corners)
        imagefilledrectangle($gdImage, $x + $radius, $y, $x2 - $radius, $y2, $color);
        imagefilledrectangle($gdImage, $x, $y + $radius, $x2, $y2 - $radius, $color);
        
        // Corner circles
        imagefilledellipse($gdImage, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($gdImage, $x2 - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($gdImage, $x + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($gdImage, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * Draw leaf shape
     */
    protected function drawLeafShape($gdImage, float $x, float $y, float $width, float $height, $color, bool $topRightBottomLeft = true): void
    {
        $x = (int)$x;
        $y = (int)$y;
        $width = (int)$width;
        $height = (int)$height;
        $radius = (int)(min($width, $height) * 0.4);
        
        // Use width-1 and height-1 for correct pixel bounds
        $x2 = $x + $width - 1;
        $y2 = $y + $height - 1;

        imagefilledrectangle($gdImage, $x + $radius, $y, $x2 - $radius, $y2, $color);
        imagefilledrectangle($gdImage, $x, $y + $radius, $x2, $y2 - $radius, $color);

        if ($topRightBottomLeft) {
            imagefilledellipse($gdImage, $x2 - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($gdImage, $x + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
            imagefilledrectangle($gdImage, $x, $y, $x + $radius, $y + $radius, $color);
            imagefilledrectangle($gdImage, $x2 - $radius, $y2 - $radius, $x2, $y2, $color);
        } else {
            imagefilledellipse($gdImage, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
            imagefilledellipse($gdImage, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
            imagefilledrectangle($gdImage, $x2 - $radius, $y, $x2, $y + $radius, $color);
            imagefilledrectangle($gdImage, $x, $y2 - $radius, $x + $radius, $y2, $color);
        }
    }

    /**
     * Add logo
     */
    protected function addLogo($canvas, array $design, int $size, int $offsetY)
    {
        $logoData = $design['logo'];
        
        if (is_string($logoData) && preg_match('/^data:image/', $logoData)) {
            $logoBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $logoData);
            $logoImageData = base64_decode($logoBase64);
        } elseif (is_array($logoData) && !empty($logoData['url'])) {
            $logoUrl = $logoData['url'];
            if (preg_match('/^data:image/', $logoUrl)) {
                $logoBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $logoUrl);
                $logoImageData = base64_decode($logoBase64);
            } else {
                $cacheKey = 'qr_logo:' . sha1($logoUrl);
                $logoImageData = Cache::remember($cacheKey, now()->addHours(6), function () use ($logoUrl) {
                    return $this->fetchRemoteImage($logoUrl);
                });
            }
        } else {
            return $canvas;
        }

        if (empty($logoImageData)) {
            return $canvas;
        }

        try {
            $logo = $this->imageManager->read($logoImageData);
            $logoSizeRatio = $design['logo_size'] ?? 0.25;
            $logoPixels = (int)($size * $logoSizeRatio);
            $logo->resize($logoPixels, $logoPixels);

            $canvasGd = $canvas->core()->native();
            $logoGd = $logo->core()->native();
            $logoWidth = imagesx($logoGd);
            $logoHeight = imagesy($logoGd);
            
            if ($design['logo_background'] ?? false) {
                $padding = 8;
                $bgSize = $logoPixels + $padding * 2;
                $logoBgGd = imagecreatetruecolor($bgSize, $bgSize);
                $white = imagecolorallocate($logoBgGd, 255, 255, 255);
                imagefilledrectangle($logoBgGd, 0, 0, $bgSize - 1, $bgSize - 1, $white);
                $bgX = ($bgSize - $logoWidth) / 2;
                $bgY = ($bgSize - $logoHeight) / 2;
                imagecopy($logoBgGd, $logoGd, (int)$bgX, (int)$bgY, 0, 0, $logoWidth, $logoHeight);
                $logoGd = $logoBgGd;
                $logoWidth = $bgSize;
                $logoHeight = $bgSize;
            }

            $logoX = (int)(($size - $logoWidth) / 2);
            $logoY = $offsetY + (int)(($size - $logoHeight) / 2);
            imagecopy($canvasGd, $logoGd, $logoX, $logoY, 0, 0, $logoWidth, $logoHeight);
            
            ob_start();
            imagepng($canvasGd);
            $canvasData = ob_get_clean();
            $canvas = $this->imageManager->read($canvasData);
        } catch (\Exception $e) {
            // Logo failed
        }

        return $canvas;
    }

    /**
     * Measure the height needed for a text area using actual font metrics.
     * Accounts for ascenders, descenders, and adds proportional padding.
     */
    protected function measureTextAreaHeight(string $text, int $fontSize, string $fontName): int
    {
        $fontFile = $this->getFontPath($fontName);
        if (!file_exists($fontFile) || !is_readable($fontFile)) {
            $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        }

        $bbox = @imagettfbbox($fontSize, 0, $fontFile, $text);
        if ($bbox === false) {
            $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
            $bbox = @imagettfbbox($fontSize, 0, $fontFile, $text);
        }

        if ($bbox !== false) {
            $textHeight = abs($bbox[1] - $bbox[7]);
        } else {
            $textHeight = $fontSize;
        }

        $padding = max(10, (int)($fontSize * 0.5));
        return $textHeight + $padding;
    }

    /**
     * Add text using GD directly
     */
    protected function addText($canvas, string $text, string $position, array $design, int $width, int $areaY, int $areaHeight)
    {
        if (empty($text)) {
            return $canvas;
        }

        $prefix = 'text_' . $position . '_';
        $fontSize = (int)($design[$prefix . 'size'] ?? 16);
        $color = $this->hexToRgb($design[$prefix . 'color'] ?? '#000000');
        $hasOutline = $design[$prefix . 'outline'] ?? false;
        $outlineColor = $this->hexToRgb($design[$prefix . 'outline_color'] ?? '#FFFFFF');
        $outlineWidth = (int)($design[$prefix . 'outline_width'] ?? 2);

        $fontFile = $this->getFontPath($design[$prefix . 'font'] ?? 'Inter');
        
        // Verify font file exists and is readable
        if (!file_exists($fontFile) || !is_readable($fontFile)) {
            \Log::error("Font file not accessible: {$fontFile}");
            $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'; // Fallback to default
        }
        
        // Get text bounding box
        $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
        
        // Check if imagettfbbox failed (returns false on error)
        if ($bbox === false) {
            \Log::error("Failed to load font: {$fontFile}, falling back to default");
            $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
            $bbox = imagettfbbox($fontSize, 0, $fontFile, $text);
            if ($bbox === false) {
                \Log::error("Default font also failed to load!");
                return $canvas; // Can't render text without a working font
            }
        }
        
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textActualHeight = abs($bbox[1] - $bbox[7]);
        $baselineOffset = -$bbox[7]; // distance from top of text to baseline

        $x = (int)(($width - $textWidth) / 2);
        // Vertically center using actual rendered height, then place at baseline
        $y = $areaY + (int)(($areaHeight - $textActualHeight) / 2) + $baselineOffset;

        $canvasGd = $canvas->core()->native();
        $textColor = imagecolorallocate($canvasGd, $color['r'], $color['g'], $color['b']);
        $outlineTextColor = imagecolorallocate($canvasGd, $outlineColor['r'], $outlineColor['g'], $outlineColor['b']);

        // Suppress warnings from imagettftext but log errors
        $errorHandler = function($errno, $errstr) use ($fontFile) {
            if (strpos($errstr, 'font') !== false || strpos($errstr, 'imagettf') !== false) {
                \Log::error("Font rendering error: {$errstr} (Font: {$fontFile})");
            }
            return true; // Suppress the error
        };
        set_error_handler($errorHandler, E_WARNING | E_NOTICE);

        try {
            if ($hasOutline) {
                for ($ox = -$outlineWidth; $ox <= $outlineWidth; $ox++) {
                    for ($oy = -$outlineWidth; $oy <= $outlineWidth; $oy++) {
                        if ($ox !== 0 || $oy !== 0) {
                            $result = @imagettftext($canvasGd, $fontSize, 0, $x + $ox, $y + $oy, $outlineTextColor, $fontFile, $text);
                            if ($result === false) {
                                \Log::warning("Failed to render text outline with font: {$fontFile}");
                            }
                        }
                    }
                }
            }

            $result = @imagettftext($canvasGd, $fontSize, 0, $x, $y, $textColor, $fontFile, $text);
            if ($result === false) {
                \Log::error("Failed to render text with font: {$fontFile}, falling back to default");
                // Try with default font as last resort
                $defaultFont = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
                $bbox = imagettfbbox($fontSize, 0, $defaultFont, $text);
                if ($bbox !== false) {
                    $textWidth = abs($bbox[2] - $bbox[0]);
                    $x = (int)(($width - $textWidth) / 2);
                    imagettftext($canvasGd, $fontSize, 0, $x, $y, $textColor, $defaultFont, $text);
                }
            }
        } finally {
            restore_error_handler();
        }

        // Convert back to Intervention Image
        ob_start();
        imagepng($canvasGd);
        $canvasData = ob_get_clean();
        return $this->imageManager->read($canvasData);
    }

    /**
     * Apply border using GD
     */
    protected function applyBorder($canvas, array $borderConfig, int $originalWidth, int $originalHeight, ?string $backgroundColor = null, ?array $backgroundGradient = null)
    {
        $borderWidth = (int)($borderConfig['width'] ?? 2);
        $color = $this->hexToRgb($borderConfig['color'] ?? '#000000');
        $radius = (int)($borderConfig['radius'] ?? 0);
        $backgroundColor = $backgroundColor ?: '#FFFFFF';

        $newWidth = $originalWidth + ($borderWidth * 2);
        $newHeight = $originalHeight + ($borderWidth * 2);
        
        $canvasGd = $canvas->core()->native();
        $borderedGd = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($borderedGd, true);
        imagesavealpha($borderedGd, true);

        $bgGd = null;
        $bgColor = null;
        if ($backgroundGradient) {
            $bgImage = $this->createGradientImage($newWidth, $newHeight, $backgroundGradient);
            $bgGd = $bgImage->core()->native();
            imagecopy($borderedGd, $bgGd, 0, 0, 0, 0, $newWidth, $newHeight);
        } else {
            $bgRgb = $this->hexToRgb($backgroundColor);
            $bgColor = imagecolorallocate($borderedGd, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
            imagefilledrectangle($borderedGd, 0, 0, $newWidth - 1, $newHeight - 1, $bgColor);
        }
        
        $borderColor = imagecolorallocate($borderedGd, $color['r'], $color['g'], $color['b']);
        if ($radius > 0) {
            $this->drawRoundedRect($borderedGd, 0, 0, $newWidth, $newHeight, $radius, $borderColor);
        } else {
            imagefilledrectangle($borderedGd, 0, 0, $newWidth - 1, $newHeight - 1, $borderColor);
        }
        imagecopy($borderedGd, $canvasGd, $borderWidth, $borderWidth, 0, 0, $originalWidth, $originalHeight);

        $innerRadius = max(0, $radius - $borderWidth);
        if ($innerRadius > 0) {
            $mask = imagecreatetruecolor($originalWidth, $originalHeight);
            imagealphablending($mask, false);
            imagesavealpha($mask, true);
            $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
            imagefill($mask, 0, 0, $transparent);
            $maskColor = imagecolorallocatealpha($mask, 255, 255, 255, 0);
            $this->drawRoundedRect($mask, 0, 0, $originalWidth, $originalHeight, $innerRadius, $maskColor);

            for ($y = 0; $y < $originalHeight; $y++) {
                for ($x = 0; $x < $originalWidth; $x++) {
                    $maskPixel = imagecolorat($mask, $x, $y);
                    $alpha = ($maskPixel >> 24) & 0x7F;
                    if ($alpha !== 0) {
                        $dstX = $x + $borderWidth;
                        $dstY = $y + $borderWidth;
                        if ($bgGd) {
                            $bgPixel = imagecolorat($bgGd, $dstX, $dstY);
                            imagesetpixel($borderedGd, $dstX, $dstY, $bgPixel);
                        } else {
                            imagesetpixel($borderedGd, $dstX, $dstY, $bgColor);
                        }
                    }
                }
            }

            imagedestroy($mask);
        }
        
        ob_start();
        imagepng($borderedGd);
        $borderedData = ob_get_clean();
        imagedestroy($borderedGd);
        
        return $this->imageManager->read($borderedData);
    }

    /**
     * Add a white "paper" margin around the composed QR so preview reflects real print.
     */
    protected function addPaperMargin($canvas, int $margin = 12, string $color = '#FFFFFF')
    {
        $margin = max(0, $margin);
        if ($margin === 0) {
            return $canvas;
        }

        $rgb = $this->hexToRgb($color);
        $src = $canvas->core()->native();
        $w = imagesx($src);
        $h = imagesy($src);

        $newW = $w + ($margin * 2);
        $newH = $h + ($margin * 2);

        $paper = imagecreatetruecolor($newW, $newH);
        imagealphablending($paper, false);
        imagesavealpha($paper, false);
        $bg = imagecolorallocate($paper, $rgb['r'], $rgb['g'], $rgb['b']);
        imagefill($paper, 0, 0, $bg);

        imagecopy($paper, $src, $margin, $margin, 0, 0, $w, $h);

        // Ensure outer margin stays pure paper color with no bleed
        imagefilledrectangle($paper, 0, 0, $newW - 1, $margin - 1, $bg);                 // top
        imagefilledrectangle($paper, 0, $newH - $margin, $newW - 1, $newH - 1, $bg);     // bottom
        imagefilledrectangle($paper, 0, 0, $margin - 1, $newH - 1, $bg);                 // left
        imagefilledrectangle($paper, $newW - $margin, 0, $newW - 1, $newH - 1, $bg);     // right

        ob_start();
        imagepng($paper);
        $data = ob_get_clean();
        imagedestroy($paper);

        return $this->imageManager->read($data);
    }

    /**
     * Apply glow effect around the QR code (smooth concentric layers like CSS box-shadow)
     *
     * Instead of GD's limited Gaussian blur (tiny kernel, poor results), we draw
     * concentric filled shapes from outermost (faintest) to innermost (brightest).
     * Each layer adds a small semi-transparent tint of the glow color.  Pixels near
     * the QR edge are covered by many layers and accumulate high opacity, while
     * pixels at the outer fringe are covered by only a few layers and stay faint.
     * The result closely matches CSS `box-shadow: 0 0 <blur>px <spread>px <color>`.
     */
    protected function applyGlow($canvas, array $glowConfig, ?string $backgroundColor = null, ?array $backgroundGradient = null, int $cornerRadius = 0, bool $previewMode = false)
    {
        $glowColor = $this->hexToRgb($glowConfig['color'] ?? '#6366F1');
        $intensity = max(0, min(50, (int)($glowConfig['intensity'] ?? 15)));
        $spread = max(0, min(20, (int)($glowConfig['spread'] ?? 5)));
        $backgroundColor = $backgroundColor ?: '#FFFFFF';
        $cornerRadius = max(0, $cornerRadius);

        $canvasGd = $canvas->core()->native();
        $originalWidth = imagesx($canvasGd);
        $originalHeight = imagesy($canvasGd);

        // Blur distance – how far the glow fades beyond the solid spread band
        $blurDist = (int) round($intensity * 1.8);
        $blurDist = max(4, min(120, $blurDist));

        // Total extra space on each side = spread (solid) + blur (fade)
        $totalGlow = $spread + $blurDist;

        $newWidth  = $originalWidth  + ($totalGlow * 2);
        $newHeight = $originalHeight + ($totalGlow * 2);

        // --- Create result canvas with background ---
        $result = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($result, true);
        imagesavealpha($result, true);

        if ($backgroundGradient) {
            $bgImage = $this->createGradientImage($newWidth, $newHeight, $backgroundGradient);
            $bgGd = $bgImage->core()->native();
            imagecopy($result, $bgGd, 0, 0, 0, 0, $newWidth, $newHeight);
        } else {
            $bgRgb = $this->hexToRgb($backgroundColor);
            $bg = imagecolorallocate($result, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
            imagefill($result, 0, 0, $bg);
        }

        // --- Draw glow using concentric filled shapes (outside → inside) ---
        // In preview mode, use fewer layers (still looks good, much faster)
        $numLayers = $previewMode
            ? max(6, min(12, $totalGlow))
            : max(15, min(60, $totalGlow));

        // Per-layer alpha (GD 0-127 scale, 0 = opaque, 127 = transparent)
        // Calibrated so accumulated opacity at the QR edge is:
        //   intensity  5  → ~30%
        //   intensity 15  → ~65%
        //   intensity 50  → ~97%
        $layerAlpha = max(3, min(8, (int) round(3 + $intensity * 0.08)));
        $gdAlpha    = 127 - $layerAlpha;

        $glowColorPacked = imagecolorallocatealpha(
            $result,
            $glowColor['r'],
            $glowColor['g'],
            $glowColor['b'],
            $gdAlpha
        );

        for ($i = 0; $i < $numLayers; $i++) {
            // progress: 0 = outermost layer, 1 = innermost layer
            $progress = $i / max(1, $numLayers - 1);

            // Distance this layer extends from the QR code edge
            // Outermost = totalGlow, innermost = spread
            $dist = $totalGlow - (int) round($progress * $blurDist);

            // Shape bounds on the result canvas
            $x1 = $totalGlow - $dist;
            $y1 = $totalGlow - $dist;
            $shapeW = $originalWidth  + ($dist * 2);
            $shapeH = $originalHeight + ($dist * 2);

            if ($shapeW <= 0 || $shapeH <= 0) {
                continue;
            }

            if ($cornerRadius > 0) {
                $effectiveRadius = max(0, $cornerRadius + $dist);
                $this->drawRoundedRect($result, $x1, $y1, $shapeW, $shapeH, $effectiveRadius, $glowColorPacked);
            } else {
                imagefilledrectangle($result, $x1, $y1, $x1 + $shapeW - 1, $y1 + $shapeH - 1, $glowColorPacked);
            }
        }

        // Place original image on top (solid copy – overwrites glow underneath)
        imagecopy($result, $canvasGd, $totalGlow, $totalGlow, 0, 0, $originalWidth, $originalHeight);

        // Convert back to Intervention Image
        ob_start();
        imagepng($result);
        $resultData = ob_get_clean();
        imagedestroy($result);

        return $this->imageManager->read($resultData);
    }

    /**
     * Apply drop shadow effect behind the QR code
     */
    protected function applyShadow($canvas, array $shadowConfig, ?string $backgroundColor = null, ?array $backgroundGradient = null, int $cornerRadius = 0, bool $previewMode = false)
    {
        $shadowColor = $this->hexToRgb($shadowConfig['color'] ?? '#000000');
        $opacity = (float)($shadowConfig['opacity'] ?? 0.3);
        $blur = (int)($shadowConfig['blur'] ?? 10);
        $offsetX = (int)($shadowConfig['offsetX'] ?? 5);
        $offsetY = (int)($shadowConfig['offsetY'] ?? 5);
        $backgroundColor = $backgroundColor ?: '#FFFFFF';
        $cornerRadius = max(0, $cornerRadius);
        
        $canvasGd = $canvas->core()->native();
        $originalWidth = imagesx($canvasGd);
        $originalHeight = imagesy($canvasGd);
        
        // Calculate extra space needed for shadow
        $extraLeft = max(0, $blur - $offsetX);
        $extraRight = max(0, $blur + $offsetX);
        $extraTop = max(0, $blur - $offsetY);
        $extraBottom = max(0, $blur + $offsetY);
        
        $newWidth = $originalWidth + $extraLeft + $extraRight;
        $newHeight = $originalHeight + $extraTop + $extraBottom;
        
        // Create new canvas with space for shadow
        $shadowCanvas = imagecreatetruecolor($newWidth, $newHeight);
        
        // Enable alpha blending
        imagealphablending($shadowCanvas, true);
        imagesavealpha($shadowCanvas, true);
        
        if ($backgroundGradient) {
            $bgImage = $this->createGradientImage($newWidth, $newHeight, $backgroundGradient);
            $bgGd = $bgImage->core()->native();
            imagecopy($shadowCanvas, $bgGd, 0, 0, 0, 0, $newWidth, $newHeight);
        } else {
            $bgRgb = $this->hexToRgb($backgroundColor);
            $bg = imagecolorallocate($shadowCanvas, $bgRgb['r'], $bgRgb['g'], $bgRgb['b']);
            imagefill($shadowCanvas, 0, 0, $bg);
        }
        
        // Draw shadow layers (blur simulation)
        $shadowAlpha = (int)(127 * (1 - $opacity));
        $shadowAlpha = max(0, min(127, $shadowAlpha));
        
        // Position for shadow
        $shadowX = $extraLeft + $offsetX;
        $shadowY = $extraTop + $offsetY;
        
        // Draw multiple blur layers (larger step in preview mode for speed)
        $step = $previewMode ? 4 : 2;
        for ($i = $blur; $i > 0; $i -= $step) {
            $layerAlpha = (int)($shadowAlpha + ((127 - $shadowAlpha) * ($i / $blur) * 0.7));
            $layerAlpha = max(0, min(127, $layerAlpha));
            $layerColor = imagecolorallocatealpha($shadowCanvas, $shadowColor['r'], $shadowColor['g'], $shadowColor['b'], $layerAlpha);
            
            // Draw shadow layer slightly larger for blur effect
            $blurOffset = $blur - $i;
            $shadowLayerX = $shadowX - $blurOffset;
            $shadowLayerY = $shadowY - $blurOffset;
            $shadowLayerW = $originalWidth + ($blurOffset * 2);
            $shadowLayerH = $originalHeight + ($blurOffset * 2);
            if ($cornerRadius > 0) {
                $this->drawRoundedRect(
                    $shadowCanvas,
                    $shadowLayerX,
                    $shadowLayerY,
                    $shadowLayerW,
                    $shadowLayerH,
                    $cornerRadius + $blurOffset,
                    $layerColor
                );
            } else {
                imagefilledrectangle(
                    $shadowCanvas,
                    $shadowLayerX,
                    $shadowLayerY,
                    $shadowLayerX + $shadowLayerW - 1,
                    $shadowLayerY + $shadowLayerH - 1,
                    $layerColor
                );
            }
        }
        
        // Draw the core shadow
        $coreColor = imagecolorallocatealpha($shadowCanvas, $shadowColor['r'], $shadowColor['g'], $shadowColor['b'], $shadowAlpha);
        if ($cornerRadius > 0) {
            $this->drawRoundedRect(
                $shadowCanvas,
                $shadowX,
                $shadowY,
                $originalWidth,
                $originalHeight,
                $cornerRadius,
                $coreColor
            );
        } else {
            imagefilledrectangle($shadowCanvas, $shadowX, $shadowY, $shadowX + $originalWidth - 1, $shadowY + $originalHeight - 1, $coreColor);
        }
        
        // Place original image on top
        $imageX = $extraLeft;
        $imageY = $extraTop;
        imagecopy($shadowCanvas, $canvasGd, $imageX, $imageY, 0, 0, $originalWidth, $originalHeight);
        
        // Convert back to Intervention Image
        ob_start();
        imagepng($shadowCanvas);
        $resultData = ob_get_clean();
        imagedestroy($shadowCanvas);
        
        return $this->imageManager->read($resultData);
    }

    /**
     * Draw background for full canvas
     */
    protected function drawBackgroundFull($image, int $width, int $height, array $design): void
    {
        if (!empty($design['background_gradient'])) {
            $gradient = $design['background_gradient'];
            $colors = $this->parseGradientColors($gradient);
            $type = $gradient['type'] ?? 'linear';
            $angle = $gradient['angle'] ?? 135;

            if ($type === 'radial') {
                $this->drawRadialGradientRect($image, $width, $height, $colors);
            } else {
                $this->drawLinearGradientRect($image, $width, $height, $colors, $angle);
            }
        } else {
            $bgColor = $this->hexToRgb($design['background_color'] ?: '#FFFFFF');
            $color = imagecolorallocate($image, $bgColor['r'], $bgColor['g'], $bgColor['b']);
            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);
        }
    }

    /**
     * Draw linear gradient on rectangular image
     * Uses a pre-computed color lookup table (LUT) instead of per-pixel interpolation.
     * This is ~10-30x faster than the naive pixel-by-pixel approach.
     */
    protected function drawLinearGradientRect($image, int $width, int $height, array $colors, float $angle): void
    {
        $radians = deg2rad($angle);
        $cos = cos($radians);
        $sin = sin($radians);
        $diagonal = sqrt($width * $width + $height * $height);
        $halfDiag = $diagonal / 2;

        // Pre-compute a 256-entry color LUT (covers 0-100% in ~0.4% steps)
        $lutSize = 256;
        $lut = [];
        for ($i = 0; $i < $lutSize; $i++) {
            $pos = ($i / ($lutSize - 1)) * 100;
            $rgb = $this->interpolateGradientColor($colors, $pos);
            $lut[$i] = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        }

        $halfW = $width / 2;
        $halfH = $height / 2;
        $lutMax = $lutSize - 1;

        for ($y = 0; $y < $height; $y++) {
            $py = $y - $halfH;
            $pysin = $py * $sin;
            for ($x = 0; $x < $width; $x++) {
                $px = $x - $halfW;
                $pos = ($px * $cos + $pysin + $halfDiag) / $diagonal;
                // Map 0..1 to LUT index
                $idx = (int)($pos * $lutMax);
                if ($idx < 0) $idx = 0;
                elseif ($idx > $lutMax) $idx = $lutMax;
                imagesetpixel($image, $x, $y, $lut[$idx]);
            }
        }
    }

    /**
     * Draw radial gradient on rectangular image
     * Uses a pre-computed color LUT and squared distances to avoid per-pixel sqrt + interpolation.
     */
    protected function drawRadialGradientRect($image, int $width, int $height, array $colors): void
    {
        $centerX = $width / 2;
        $centerY = $height / 2;
        $maxRadius = sqrt($centerX * $centerX + $centerY * $centerY);

        // Pre-compute a 256-entry color LUT
        $lutSize = 256;
        $lut = [];
        for ($i = 0; $i < $lutSize; $i++) {
            $pos = ($i / ($lutSize - 1)) * 100;
            $rgb = $this->interpolateGradientColor($colors, $pos);
            $lut[$i] = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        }

        $lutMax = $lutSize - 1;
        $invMaxRadius = 1.0 / max(1, $maxRadius);

        for ($y = 0; $y < $height; $y++) {
            $dy = $y - $centerY;
            $dy2 = $dy * $dy;
            for ($x = 0; $x < $width; $x++) {
                $dx = $x - $centerX;
                $distance = sqrt($dx * $dx + $dy2);
                $idx = (int)(($distance * $invMaxRadius) * $lutMax);
                if ($idx > $lutMax) $idx = $lutMax;
                imagesetpixel($image, $x, $y, $lut[$idx]);
            }
        }
    }

    /**
     * Parse gradient colors
     */
    protected function parseGradientColors(array $gradient): array
    {
        $colors = [];
        
        if (isset($gradient['colors']) && is_array($gradient['colors'])) {
            foreach ($gradient['colors'] as $stop) {
                if (is_array($stop) && isset($stop['color'])) {
                    $colors[] = [
                        'color' => $this->hexToRgb($stop['color']),
                        'position' => $stop['position'] ?? 0
                    ];
                } elseif (is_string($stop)) {
                    $colors[] = [
                        'color' => $this->hexToRgb($stop),
                        'position' => count($colors) * 100 / max(1, count($gradient['colors']) - 1)
                    ];
                }
            }
        }

        if (count($colors) < 2) {
            $colors = [
                ['color' => ['r' => 0, 'g' => 0, 'b' => 0], 'position' => 0],
                ['color' => ['r' => 51, 'g' => 51, 'b' => 51], 'position' => 100]
            ];
        }

        usort($colors, fn($a, $b) => $a['position'] <=> $b['position']);

        return $colors;
    }

    /**
     * Interpolate color from gradient stops
     */
    protected function interpolateGradientColor(array $colors, float $position): array
    {
        if (empty($colors)) {
            return ['r' => 0, 'g' => 0, 'b' => 0];
        }
        
        $prevColor = $colors[0];
        $nextColor = $colors[count($colors) - 1];

        for ($i = 0; $i < count($colors) - 1; $i++) {
            if ($position >= $colors[$i]['position'] && $position <= $colors[$i + 1]['position']) {
                $prevColor = $colors[$i];
                $nextColor = $colors[$i + 1];
                break;
            }
        }

        $range = $nextColor['position'] - $prevColor['position'];
        $ratio = $range > 0 ? ($position - $prevColor['position']) / $range : 0;
        $ratio = max(0, min(1, $ratio)); // Clamp ratio

        // Safely get color values with defaults
        $prevR = $prevColor['color']['r'] ?? 0;
        $prevG = $prevColor['color']['g'] ?? 0;
        $prevB = $prevColor['color']['b'] ?? 0;
        $nextR = $nextColor['color']['r'] ?? 0;
        $nextG = $nextColor['color']['g'] ?? 0;
        $nextB = $nextColor['color']['b'] ?? 0;

        return [
            'r' => max(0, min(255, (int)round($prevR + ($nextR - $prevR) * $ratio))),
            'g' => max(0, min(255, (int)round($prevG + ($nextG - $prevG) * $ratio))),
            'b' => max(0, min(255, (int)round($prevB + ($nextB - $prevB) * $ratio))),
        ];
    }

    /**
     * Build a stable cache key for previews
     */
    protected function makePreviewCacheKey(string $data, array $design): string
    {
        return 'qr_preview:' . sha1($data . '|' . json_encode($design));
    }

    /**
     * Fetch a remote image safely (short timeout, size cap)
     */
    protected function fetchRemoteImage(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'follow_location' => 1,
                'max_redirects' => 2,
            ],
            'https' => [
                'timeout' => 2,
                'follow_location' => 1,
                'max_redirects' => 2,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            return null;
        }

        // Hard cap at 2MB to prevent oversized downloads blocking generation
        if (strlen($data) > 2 * 1024 * 1024) {
            return null;
        }

        return $data;
    }

    /**
     * Get font path
     */
    protected function getFontPath(string $font): string
    {
        $defaultFont = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fontsDir = storage_path('app/fonts');
        
        // Map font names to actual font files
        // Use downloaded Google Fonts to ensure the selected face renders as expected
        $fontMap = [
            'Inter' => $fontsDir . '/Inter-Regular.ttf',
            'Poppins' => $fontsDir . '/Poppins-Regular.ttf',
            'Playfair Display' => $fontsDir . '/PlayfairDisplay-Regular.ttf',
            'Merriweather' => $fontsDir . '/Merriweather-Regular.ttf',
            'Oswald' => $fontsDir . '/Oswald-Regular.ttf',
            'Bebas Neue' => $fontsDir . '/BebasNeue-Regular.ttf',
            'Pacifico' => $fontsDir . '/Pacifico-Regular.ttf',
            'Dancing Script' => $fontsDir . '/DancingScript-Regular.ttf',
            'Permanent Marker' => $fontsDir . '/PermanentMarker-Regular.ttf',
            'Indie Flower' => $fontsDir . '/IndieFlower-Regular.ttf',
            'Amatic SC' => $fontsDir . '/AmaticSC-Regular.ttf',
            'Fredoka One' => $fontsDir . '/FredokaOne-Regular.ttf',
            // Legacy aliases (older saved designs fall back to Inter)
            'Roboto' => $fontsDir . '/Inter-Regular.ttf',
            'Open Sans' => $fontsDir . '/Inter-Regular.ttf',
            'Montserrat' => $fontsDir . '/Inter-Regular.ttf',
            'Lato' => $fontsDir . '/Inter-Regular.ttf',
            'Raleway' => $fontsDir . '/Inter-Regular.ttf',
            'Source Sans Pro' => $fontsDir . '/Inter-Regular.ttf',
            'Ubuntu' => $fontsDir . '/Inter-Regular.ttf',
            'Nunito' => $fontsDir . '/Inter-Regular.ttf',
            'Comfortaa' => $fontsDir . '/Inter-Regular.ttf',
            'Courier New' => $defaultFont,
            'Monospace' => $defaultFont,
            'Noto Mono' => $defaultFont,
        ];

        $fontPath = $fontMap[$font] ?? $defaultFont;
        
        // Verify font file exists and is readable, fallback to default if not
        if (!file_exists($fontPath)) {
            \Log::warning("Font file not found: {$fontPath} (requested font: {$font}), using default font");
            return $defaultFont;
        }
        
        if (!is_readable($fontPath)) {
            \Log::warning("Font file not readable: {$fontPath} (requested font: {$font}), using default font");
            return $defaultFont;
        }
        
        // Test if font can be loaded by GD
        $testBbox = @imagettfbbox(12, 0, $fontPath, 'A');
        if ($testBbox === false) {
            \Log::warning("Font file cannot be loaded by GD: {$fontPath} (requested font: {$font}), using default font");
            return $defaultFont;
        }
        
        return $fontPath;
    }

    /**
     * Scale design properties proportionally to a new canvas size.
     * Used to upscale for high-quality output or downscale for performance.
     */
    protected function scaleDesignForSize(array $design, int $targetSize): array
    {
        $currentSize = (int)($design['size'] ?? 300);
        if ($currentSize === $targetSize || $currentSize <= 0) {
            return $design;
        }

        $scale = $targetSize / $currentSize;
        $design['size'] = $targetSize;
        $design['margin'] = max(1, (int) round(($design['margin'] ?? 4) * $scale));

        // Scale text sizes and outline widths
        foreach (['top', 'bottom'] as $pos) {
            $sizeKey = "text_{$pos}_size";
            $outlineKey = "text_{$pos}_outline_width";
            if (isset($design[$sizeKey])) {
                $design[$sizeKey] = max(12, (int) round($design[$sizeKey] * $scale));
            }
            if (isset($design[$outlineKey])) {
                $design[$outlineKey] = max(1, (int) round($design[$outlineKey] * $scale));
            }
        }

        // Scale border properties
        if (!empty($design['border']) && is_array($design['border'])) {
            if (isset($design['border']['width'])) {
                $design['border']['width'] = max(1, (int) round($design['border']['width'] * $scale));
            }
            if (isset($design['border']['radius'])) {
                $design['border']['radius'] = max(0, (int) round($design['border']['radius'] * $scale));
            }
        }

        // Scale glow properties
        if (!empty($design['glow']) && is_array($design['glow'])) {
            if (isset($design['glow']['intensity'])) {
                $design['glow']['intensity'] = max(0, (int) round($design['glow']['intensity'] * $scale));
            }
            if (isset($design['glow']['spread'])) {
                $design['glow']['spread'] = max(0, (int) round($design['glow']['spread'] * $scale));
            }
        }

        // Scale shadow properties
        if (!empty($design['shadow']) && is_array($design['shadow'])) {
            if (isset($design['shadow']['blur'])) {
                $design['shadow']['blur'] = max(0, (int) round($design['shadow']['blur'] * $scale));
            }
            if (isset($design['shadow']['offsetX'])) {
                $design['shadow']['offsetX'] = (int) round($design['shadow']['offsetX'] * $scale);
            }
            if (isset($design['shadow']['offsetY'])) {
                $design['shadow']['offsetY'] = (int) round($design['shadow']['offsetY'] * $scale);
            }
        }

        return $design;
    }

    /**
     * Generate QR code as downloadable file
     */
    public function generateFile(string $data, array $design, string $format = 'png'): string
    {
        // Force high resolution for final output to ensure sharp, crisp text and modules.
        // GD renders text much better at larger canvas sizes with larger font sizes.
        $minFinalSize = 1000;
        $currentSize = (int)($design['size'] ?? 300);
        if ($currentSize < $minFinalSize) {
            $design = $this->scaleDesignForSize($design, $minFinalSize);
        }

        // Never use preview_mode for final file output
        $design['preview_mode'] = false;

        $base64 = $this->generate($data, $design);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        
        $filename = 'qr_' . uniqid() . '.' . $format;
        $directory = storage_path('app/public/qrcodes');
        $path = $directory . '/' . $filename;
        
        // Ensure directory exists with proper permissions
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
            chmod($directory, 0775);
        }

        // Write file with error handling
        if (file_put_contents($path, $imageData) === false) {
            throw new \RuntimeException("Failed to write QR code image to: {$path}");
        }

        // Set proper permissions
        chmod($path, 0664);

        return 'qrcodes/' . $filename;
    }

    protected function getEccLevel(string $level): int
    {
        return match (strtoupper($level)) {
            'L' => QRCode::ECC_L,
            'M' => QRCode::ECC_M,
            'Q' => QRCode::ECC_Q,
            'H' => QRCode::ECC_H,
            default => QRCode::ECC_M,
        };
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return ['r' => 0, 'g' => 0, 'b' => 0];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }
    
    /**
     * Apply color mapping to change black/white to custom colors
     */
    protected function applyColorMapping($image, array $moduleColor, array $bgColor)
    {
        $imageGd = $image->core()->native();
        $width = imagesx($imageGd);
        $height = imagesy($imageGd);
        
        $newModuleColor = imagecolorallocate($imageGd, $moduleColor['r'], $moduleColor['g'], $moduleColor['b']);
        $newBgColor = imagecolorallocate($imageGd, $bgColor['r'], $bgColor['g'], $bgColor['b']);
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($imageGd, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // If pixel is dark (black or near-black), use module color
                // If pixel is light (white or near-white), use bg color
                $isDark = ($r < 128 && $g < 128 && $b < 128);
                $newColor = $isDark ? $newModuleColor : $newBgColor;
                imagesetpixel($imageGd, $x, $y, $newColor);
            }
        }
        
        ob_start();
        imagepng($imageGd);
        $newData = ob_get_clean();
        return $this->imageManager->read($newData);
    }
}
