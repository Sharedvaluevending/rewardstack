<?php

namespace App\Http\Controllers;

use App\Mail\PromoLinkEmail;
use App\Models\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicPromoEmailController extends Controller
{
    /**
     * Guest-friendly endpoint to email a link back to a promotion QR page.
     * Rate-limited via RouteServiceProvider.
     */
    public function send(Request $request)
    {
        // In some non-production environments (CI/test containers) DNS lookups may be unavailable.
        // Keep production strict, but avoid blocking valid emails in non-production.
        $emailRule = app()->environment('production')
            ? 'required|email:rfc,dns|max:255'
            : 'required|email:rfc|max:255';

        $validated = $request->validate([
            'code' => 'required|string|size:8',
            'email' => $emailRule,
        ]);

        $qrCode = QRCode::where('code', $validated['code'])
            ->with(['promotion', 'business:id,name'])
            ->first();

        if (!$qrCode || !$qrCode->promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Promotion not found',
            ], 404);
        }

        // Always email links (no attachments) for maximum deliverability and simplicity.
        Mail::to($validated['email'])->send(new PromoLinkEmail(
            code: $qrCode->code,
            promotionName: (string) $qrCode->promotion->name,
            businessName: (string) ($qrCode->business?->name ?? 'RewardStack'),
        ));

        return response()->json([
            'success' => true,
        ]);
    }
}


