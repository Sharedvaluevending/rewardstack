<?php

namespace App\Services;

use App\Models\QRCode;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class PrintKitArtworkService
{
    protected ImageManager $images;
    protected QRGeneratorService $qrGenerator;

    public function __construct(QRGeneratorService $qrGenerator)
    {
        $this->images = new ImageManager(new Driver());
        $this->qrGenerator = $qrGenerator;
    }

    /**
     * Generate a 2" sticker sheet image (Letter @ 300dpi, 20-up 4x5) for a single QR code.
     * Returns a public storage path (relative to storage/app/public).
     */
    public function generate2InStickerSheet(QRCode $qrCode): string
    {
        // Letter @ 300dpi
        $width = 2550;  // 8.5 * 300
        $height = 3300; // 11 * 300

        // Each 2" sticker @ 300dpi
        $sticker = 600; // 2 * 300

        // 4x5 grid => 2400x3000; margins: 75 left/right, 150 top/bottom
        $marginX = 75;
        $marginY = 150;

        $canvas = $this->images->create($width, $height)->fill('#FFFFFF');

        // Generate a clean QR (no text/border) optimized for print
        $qrPath = $this->qrGenerator->generateFile(
            $qrCode->getScanUrl(),
            array_merge($qrCode->getDesignWithDefaults(), [
                'size' => $sticker,
                'margin' => 12,
                'preview_mode' => true,
                'text_top' => '',
                'text_bottom' => '',
                'border' => null,
                'glow' => null,
                'shadow' => null,
            ]),
            'png'
        );

        $qrAbs = Storage::disk('public')->path($qrPath);
        $qrImg = $this->images->read($qrAbs);

        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 4; $col++) {
                $x = $marginX + ($col * $sticker);
                $y = $marginY + ($row * $sticker);
                $canvas->place($qrImg, 'top-left', $x, $y);
            }
        }

        $outName = 'printkits/sheets/' . $qrCode->business_id . '/sheet_' . $qrCode->code . '_' . uniqid() . '.png';
        Storage::disk('public')->put($outName, (string) $canvas->toPng());
        return $outName;
    }

    /**
     * Generate a 4" decal image (square @ 300dpi) for a single QR code.
     * Returns a public storage path (relative to storage/app/public).
     */
    public function generate4InDecal(QRCode $qrCode): string
    {
        $size = 1200; // 4 * 300

        $path = $this->qrGenerator->generateFile(
            $qrCode->getScanUrl(),
            array_merge($qrCode->getDesignWithDefaults(), [
                'size' => $size,
                'margin' => 16,
                'preview_mode' => true,
                'text_top' => '',
                'text_bottom' => '',
                'border' => null,
                'glow' => null,
                'shadow' => null,
            ]),
            'png'
        );

        $outName = 'printkits/decals/' . $qrCode->business_id . '/decal_' . $qrCode->code . '_' . uniqid() . '.png';
        Storage::disk('public')->copy($path, $outName);
        return $outName;
    }
}


