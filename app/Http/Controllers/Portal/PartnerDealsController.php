<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CrossPromotion;
use App\Models\QRCode;
use App\Models\Scan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartnerDealsController extends Controller
{
    /**
     * Show customer's partner deals
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get QR codes for cross-promos that user has scanned
        $scannedQrIds = Scan::where('user_id', $user->id)
            ->whereHas('qrCode', function ($q) {
                $q->where('type', 'cross_promo')
                  ->whereNotNull('cross_promotion_id');
            })
            ->pluck('qr_code_id')
            ->unique();
        
        $crossPromos = CrossPromotion::whereHas('qrCodes', function ($q) use ($scannedQrIds) {
            $q->whereIn('id', $scannedQrIds);
        })
        ->with(['business1:id,name,logo_path', 'business2:id,name,logo_path', 'promotion1', 'promotion2'])
        ->active()
        ->get()
        ->map(function ($cp) use ($user, $scannedQrIds) {
            $qrCode = $cp->qrCodes()->whereIn('id', $scannedQrIds)->first();
            
            if (!$cp->business1 || !$cp->business2 || !$cp->promotion1 || !$cp->promotion2) {
                return null;
            }

            return [
                'id' => $cp->id,
                'code' => $cp->code,
                'name' => $cp->name,
                'chain_mode' => $cp->chain_mode,
                'qr_code' => $qrCode ? [
                    'id' => $qrCode->id,
                    'code' => $qrCode->code,
                ] : null,
                'business1' => [
                    'id' => $cp->business1->id,
                    'name' => $cp->business1->name,
                    'logo' => $cp->business1->logo_url,
                ],
                'business2' => [
                    'id' => $cp->business2->id,
                    'name' => $cp->business2->name,
                    'logo' => $cp->business2->logo_url,
                ],
                'promotion1' => [
                    'id' => $cp->promotion1->id,
                    'name' => $cp->promotion1->name,
                    'display_value' => $cp->promotion1->getDisplayDescription(),
                ],
                'promotion2' => [
                    'id' => $cp->promotion2->id,
                    'name' => $cp->promotion2->name,
                    'display_value' => $cp->promotion2->getDisplayDescription(),
                ],
            ];
        })->filter()->values();
        
        return Inertia::render('Portal/PartnerDeals', [
            'crossPromos' => $crossPromos,
        ]);
    }
}
