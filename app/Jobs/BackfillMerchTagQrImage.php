<?php

namespace App\Jobs;

use App\Models\MerchTag;
use App\Services\QRGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackfillMerchTagQrImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $merchTagId)
    {
    }

    public function handle(QRGeneratorService $qrService): void
    {
        $tag = MerchTag::with('qrCode')->find($this->merchTagId);

        if (!$tag || $tag->qr_image_path || !$tag->qrCode) {
            return;
        }

        try {
            $design = $tag->qrCode->getDesignWithDefaults();
            $imagePath = $qrService->generateFile(
                url('/m/' . $tag->code),
                $design,
                'png',
                1200
            );
            $tag->update(['qr_image_path' => $imagePath]);
        } catch (\Throwable $e) {
            Log::warning('Failed to generate merch tag QR image', [
                'merch_tag_id' => $tag->id,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Let the queue retry
        }
    }
}
