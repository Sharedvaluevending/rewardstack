<?php

namespace App\Services;

use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class PromoClaimService
{
    public function __construct(
        protected UserPromoTokenService $userPromoTokenService,
        protected BusinessCustomerService $businessCustomerService
    ) {}

    /**
     * Store a pending promo code in session so that after login/register we can auto-save
     * it into the user's portal (Scans + Saved).
     */
    public function rememberPendingPromo(Request $request, string $code): void
    {
        $request->session()->put('pending_promo_code', $code);
        $request->session()->put('pending_promo_set_at', now()->timestamp);
    }

    public function clearPendingPromo(Request $request): void
    {
        $request->session()->forget(['pending_promo_code', 'pending_promo_set_at']);
    }

    /**
     * If there's a pending promo in session, claim it for the user:
     * - save QR code in saved_qr_codes
     * - attach or create a Scan row with user_id so it appears in Portal/Scans
     */
    public function claimPendingPromo(Request $request, User $user): bool
    {
        $code = (string) $request->session()->get('pending_promo_code', '');
        if ($code === '') {
            return false;
        }

        $qrCode = QRCode::where('code', $code)
            ->where('type', 'promotion')
            ->with('promotion')
            ->first();

        if (!$qrCode || !$qrCode->promotion) {
            $this->clearPendingPromo($request);
            return false;
        }

        $this->claimPromo($request, $user, $qrCode);
        $this->clearPendingPromo($request);
        return true;
    }

    /**
     * Claim a specific promotion QR code for a user.
     */
    public function claimPromo(Request $request, User $user, QRCode $qrCode): void
    {
        // Save in portal "Saved"
        $saved = SavedQRCode::firstOrCreate(
            ['user_id' => $user->id, 'qr_code_id' => $qrCode->id],
            ['saved_at' => now()]
        );

        // Update CRM customer cache (owned customers only)
        if ($saved->wasRecentlyCreated) {
            $this->businessCustomerService->recordSaved((int) $qrCode->business_id, (int) $user->id);
        }

        // Ensure per-user promo token exists (used for staff redemption + portal linkage)
        $this->userPromoTokenService->ensure($user, $qrCode);

        // Attach an existing scan to user if it exists; otherwise create one so it shows in Portal/Scans.
        $sessionId = $request->session()->getId();

        $recentWindow = now()->subMinutes(15);

        $scan = Scan::query()
            ->where('qr_code_id', $qrCode->id)
            ->where('scanned_at', '>=', $recentWindow)
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->orderByDesc('scanned_at')
            ->first();

        if (!$scan) {
            // Fallback: if session changes, try matching by IP within a short window.
            // Only match unclaimed scans to avoid grabbing another user's scan on shared IPs.
            $scan = Scan::query()
                ->where('qr_code_id', $qrCode->id)
                ->where('scanned_at', '>=', $recentWindow)
                ->where('ip_address', $request->ip())
                ->whereNull('user_id')
                ->orderByDesc('scanned_at')
                ->first();
        }

        if ($scan) {
            if (!$scan->user_id) {
                $scan->update(['user_id' => $user->id]);
            }
            return;
        }

        // We no longer create "ghost scans" here.
        // A scan record should only be created when the physical QR code is actually scanned (via ScanController@scan).
        // This ensures business analytics reflect real-world camera scans, not just page views or share link clicks.
    }
}


