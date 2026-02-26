<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmMessage;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\User;
use App\Services\PromoClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignPromoController extends Controller
{
    public function __construct(private PromoClaimService $promoClaimService)
    {
    }

    /**
    * Signed link target: saves a campaign-attached promotion to the user's account.
    */
    public function save(Request $request)
    {
        $campaignId = (int) $request->query('campaign');
        $messageId = (int) $request->query('message');
        $promoId = (int) $request->query('promo');
        $userId = (int) $request->query('user');
        $businessId = (int) $request->query('business');
        $token = (string) $request->query('token', '');

        $message = CrmMessage::with('campaign')->find($messageId);
        if (!$message || $message->campaign_id !== $campaignId || $message->business_id !== $businessId) {
            return $this->linkError('This link is invalid or has expired.');
        }

        if ($token === '' || $message->promo_link_token !== $token) {
            return $this->linkError('This link is invalid or has expired.');
        }

        if ($message->promo_link_used_at) {
            return $this->linkError('This link has already been used.');
        }

        $campaign = $message->campaign;
        if (!$campaign || (int) $campaign->promotion_id !== $promoId) {
            return $this->linkError('This promotion is no longer available.');
        }

        $user = User::find($userId);
        if (!$user || $message->user_id !== $userId) {
            return $this->linkError('This link is not valid for this account.');
        }

        $promotion = Promotion::where('id', $promoId)
            ->where('business_id', $businessId)
            ->first();
        if (!$promotion) {
            return $this->linkError('Promotion not found.');
        }

        if (!$promotion->isCurrentlyValid()) {
            return $this->linkError('This promotion has expired.');
        }

        $qrCode = QRCode::where('promotion_id', $promotion->id)
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$qrCode) {
            return $this->linkError('No active QR code is available for this promotion.');
        }

        $message->update(['promo_link_used_at' => now()]);

        Auth::login($user);

        $this->promoClaimService->claimPromo($request, $user, $qrCode);

        return redirect()->route('portal.scans')->with('success', 'Promotion saved to your wallet.');
    }

    protected function linkError(string $message)
    {
        return redirect()->route('home')->with('error', $message);
    }
}
