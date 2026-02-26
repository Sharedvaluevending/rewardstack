<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralLandingController extends Controller
{
    /**
     * Show the referral landing page
     */
    public function show(Request $request, string $code)
    {
        // Find the referrer
        $referrer = User::where('referral_code', $code)->first();

        if (!$referrer) {
            // Invalid code - redirect to regular pricing
            return redirect()->route('pricing');
        }

        // Store the referral code in session for registration
        session(['referral_code' => $code]);

        return Inertia::render('Public/ReferralLanding', [
            'referrerName' => $referrer->name,
            'referralCode' => $code,
        ]);
    }
}
