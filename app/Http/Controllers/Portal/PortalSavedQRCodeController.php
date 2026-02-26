<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\QRCode;
use App\Models\SavedQRCode;
use App\Services\UserPromoTokenService;
use App\Support\OnboardingQr;
use Illuminate\Http\Request;

class PortalSavedQRCodeController extends Controller
{
    public function __construct(
        protected UserPromoTokenService $userPromoTokenService
    ) {}

    protected function inertiaBack(Request $request, string $flashKey, string $message)
    {
        // Inertia expects redirects after non-GET to be 303.
        return redirect()->back(303)->with($flashKey, $message);
    }

    /**
     * Save a QR code for the authenticated user
     */
    public function store(Request $request, QRCode $qrCode)
    {
        $user = $request->user();

        if ($qrCode->code === OnboardingQr::CODE) {
            $message = 'This QR code cannot be saved.';
            if ($request->header('X-Inertia')) {
                return redirect()->back(303)->withErrors(['save' => $message]);
            }
            return response()->json([
                'success' => false,
                'message' => $message,
                'saved' => false,
            ], 422);
        }

        // Check if already saved
        $existing = SavedQRCode::where('user_id', $user->id)
            ->where('qr_code_id', $qrCode->id)
            ->first();

        if ($existing) {
            if ($request->header('X-Inertia')) {
                return $this->inertiaBack($request, 'success', 'Saved');
            }

            return response()->json([
                'success' => true,
                'message' => 'QR code already saved',
                'saved' => true,
            ]);
        }

        // Save the QR code
        SavedQRCode::create([
            'user_id' => $user->id,
            'qr_code_id' => $qrCode->id,
            'saved_at' => now(),
        ]);

        // Ensure per-user promo token exists (so the portal can show a customer-specific redeem QR/code)
        $this->userPromoTokenService->ensure($user, $qrCode->loadMissing('promotion'));

        if ($request->header('X-Inertia')) {
            return $this->inertiaBack($request, 'success', 'Saved');
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code saved successfully',
            'saved' => true,
        ]);
    }

    /**
     * Unsave a QR code for the authenticated user
     */
    public function destroy(Request $request, QRCode $qrCode)
    {
        $user = $request->user();

        SavedQRCode::where('user_id', $user->id)
            ->where('qr_code_id', $qrCode->id)
            ->delete();

        if ($request->header('X-Inertia')) {
            return $this->inertiaBack($request, 'success', 'Unsaved');
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code unsaved successfully',
            'saved' => false,
        ]);
    }
}
