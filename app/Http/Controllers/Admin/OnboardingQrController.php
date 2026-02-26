<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\QRCode;
use App\Models\Scan;
use App\Models\User;
use App\Services\QRGeneratorService;
use App\Support\BusinessCardQr;
use App\Support\OnboardingQr;
use Inertia\Inertia;
use Illuminate\Http\Request;

class OnboardingQrController extends Controller
{
    public function index(Request $request, QRGeneratorService $qrService)
    {
        $businessId = config('onboarding.qr_business_id');
        $business = ($businessId ? Business::find($businessId) : null)
            ?? Business::query()->orderBy('id')->first();
        if (!$business) {
            return Inertia::render('Admin/OnboardingQr', [
                'error' => 'No business found. Create a business first to attach this QR code.',
                'qrCode' => null,
                'qrImage' => null,
                'stats' => null,
                'recentScans' => [],
                'businessCardQr' => null,
                'businessCardQrImage' => null,
                'businessCardStats' => null,
                'businessCardRecentScans' => [],
            ]);
        }

        // ── Join Flyer QR ──────────────────────────────────────────
        $qrCode = $this->ensureQr($business, OnboardingQr::CODE, OnboardingQr::NAME, OnboardingQr::destinationUrl());

        $design = array_merge($qrCode->getDesignWithDefaults(), [
            'size' => 600,
            'error_correction' => 'H',
        ]);

        $qrImage = $qrService->generate($qrCode->getScanUrl(), $design);

        $conversions = User::query()
            ->where('preferences->onboarding_qr_code', OnboardingQr::CODE)
            ->count();

        $totalScans = (int) ($qrCode->total_scans ?? 0);
        $uniqueScans = (int) ($qrCode->unique_scans ?? 0);
        $conversionRate = $totalScans > 0 ? round(($conversions / $totalScans) * 100, 1) : 0.0;

        $recentScans = $this->recentScans($qrCode->id);

        // ── Business Card QR ───────────────────────────────────────
        $bizQr = $this->ensureQr($business, BusinessCardQr::CODE, BusinessCardQr::NAME, BusinessCardQr::destinationUrl());

        $bizDesign = array_merge($bizQr->getDesignWithDefaults(), [
            'size' => 600,
            'error_correction' => 'H',
        ]);

        $bizQrImage = $qrService->generate($bizQr->getScanUrl(), $bizDesign);

        $bizTotalScans = (int) ($bizQr->total_scans ?? 0);
        $bizUniqueScans = (int) ($bizQr->unique_scans ?? 0);

        $bizRecentScans = $this->recentScans($bizQr->id);

        // ── Render ─────────────────────────────────────────────────
        return Inertia::render('Admin/OnboardingQr', [
            'error' => null,
            'qrCode' => [
                'id' => $qrCode->id,
                'code' => $qrCode->code,
                'scan_url' => $qrCode->getScanUrl(),
                'destination_url' => $qrCode->destination_url,
                'total_scans' => $totalScans,
                'unique_scans' => $uniqueScans,
                'last_scanned_at' => $qrCode->last_scanned_at?->toDateTimeString(),
            ],
            'qrImage' => $qrImage,
            'stats' => [
                'total_scans' => $totalScans,
                'unique_scans' => $uniqueScans,
                'conversions' => $conversions,
                'conversion_rate' => $conversionRate,
            ],
            'recentScans' => $recentScans,
            'businessCardQr' => [
                'id' => $bizQr->id,
                'code' => $bizQr->code,
                'scan_url' => $bizQr->getScanUrl(),
                'destination_url' => $bizQr->destination_url,
                'total_scans' => $bizTotalScans,
                'unique_scans' => $bizUniqueScans,
                'last_scanned_at' => $bizQr->last_scanned_at?->toDateTimeString(),
            ],
            'businessCardQrImage' => $bizQrImage,
            'businessCardStats' => [
                'total_scans' => $bizTotalScans,
                'unique_scans' => $bizUniqueScans,
            ],
            'businessCardRecentScans' => $bizRecentScans,
        ]);
    }

    /**
     * Ensure a system QR code exists (create or restore).
     */
    private function ensureQr(Business $business, string $code, string $name, string $destinationUrl): QRCode
    {
        $qrCode = QRCode::withTrashed()->where('code', $code)->first();

        if (!$qrCode) {
            return QRCode::create([
                'business_id' => $business->id,
                'code' => $code,
                'name' => $name,
                'type' => 'static',
                'intended_use' => QRCode::INTENDED_USE_PUBLIC,
                'destination_url' => $destinationUrl,
                'design' => ['error_correction' => 'H'],
                'is_active' => true,
            ]);
        }

        if ($qrCode->trashed()) {
            $qrCode->restore();
        }

        $design = is_array($qrCode->design) ? $qrCode->design : [];
        $design['error_correction'] = 'H';

        $updates = [
            'name' => $name,
            'type' => 'static',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'destination_url' => $destinationUrl,
            'design' => $design,
            'is_active' => true,
        ];
        if ($qrCode->business_id !== $business->id) {
            $updates['business_id'] = $business->id;
        }
        $qrCode->update($updates);

        return $qrCode;
    }

    /**
     * Fetch the most recent scans for a given QR code.
     */
    private function recentScans(int $qrCodeId)
    {
        return Scan::query()
            ->where('qr_code_id', $qrCodeId)
            ->orderByDesc('scanned_at')
            ->limit(12)
            ->get([
                'id',
                'scanned_at',
                'device_type',
                'browser',
                'os',
                'city',
                'region',
                'country',
                'referrer',
            ])
            ->values();
    }
}
