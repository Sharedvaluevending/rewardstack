<?php

namespace App\Http\Controllers;

use App\Models\MerchTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchTagController extends Controller
{
    /**
     * Scan entrypoint for merch tags.
     * If unclaimed, redirects to claim page. If claimed, proceeds to gateway QR code.
     */
    public function scan(Request $request, string $code)
    {
        $tag = MerchTag::with(['qrCode', 'business'])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$tag || !$tag->qrCode) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This merch code is not available.',
            ]);
        }

        // If tag is not claimed, redirect to claim page
        if (!$tag->owner_user_id) {
            return redirect()->route('merch.claim.view', $code);
        }

        // Stash merch tag in session so /s/{code} can attach it to the scan record.
        $request->session()->put('merch_tag_id', $tag->id);

        return redirect()->route('scan', $tag->qrCode->code);
    }

    /**
     * Show claim page for a merch tag.
     */
    public function claimView(Request $request, string $code)
    {
        $tag = MerchTag::with(['qrCode', 'business', 'owner'])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$tag || !$tag->qrCode) {
            return Inertia::render('Public/ScanError', [
                'message' => 'This merch code is not available.',
            ]);
        }

        return Inertia::render('Public/MerchClaim', [
            'tag' => [
                'code' => $tag->code,
                'is_claimed' => (bool) $tag->owner_user_id,
            ],
            'business' => [
                'name' => $tag->business?->name,
                'logo_url' => $tag->business?->logo_url,
            ],
            'qrCode' => [
                'code' => $tag->qrCode->code,
                'name' => $tag->qrCode->name,
            ],
            'owner' => $tag->owner ? [
                'name' => $tag->owner->name,
            ] : null,
        ]);
    }

    /**
     * Claim a merch tag for the current user.
     */
    public function claim(Request $request, string $code)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login', ['redirect_to' => "/m/{$code}/claim"]);
        }

        $tag = MerchTag::with('qrCode')
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$tag) {
            return redirect()->route('home')->with('error', 'Merch code not found.');
        }

        if (!$tag->qrCode) {
            return redirect()->route('home')->with('error', 'This merch code is not linked to a QR code.');
        }

        if ($tag->owner_user_id && $tag->owner_user_id !== $user->id) {
            return redirect()->route('home')->with('error', 'This merch code is already claimed.');
        }

        if (!$tag->owner_user_id) {
            // Atomic update to prevent race condition -- only claims if still unclaimed
            $affected = MerchTag::where('id', $tag->id)
                ->whereNull('owner_user_id')
                ->update([
                    'owner_user_id' => $user->id,
                    'claimed_at' => now(),
                ]);

            if ($affected === 0) {
                // Someone else claimed it between our check and update
                return redirect()->route('home')->with('error', 'This merch code is already claimed.');
            }

            $tag->refresh();
        }

        // Stash merch tag in session so /s/{code} can attach it to the scan record.
        $request->session()->put('merch_tag_id', $tag->id);

        // Redirect to the QR code scan so they can get the promotion
        return redirect()->route('scan', $tag->qrCode->code)->with('success', 'Merch claimed! You can now share this code with others.');
    }
}
