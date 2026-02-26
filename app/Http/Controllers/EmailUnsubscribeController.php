<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Services\BusinessCustomerService;
use Illuminate\Http\Request;

class EmailUnsubscribeController extends Controller
{
    /**
     * Signed unsubscribe endpoint (no login required).
     *
     * Query params are signed via URL::signedRoute / URL::temporarySignedRoute.
     */
    public function unsubscribe(Request $request, BusinessCustomerService $service)
    {
        $userId = (int) $request->query('u');
        $businessId = (int) $request->query('b');

        if ($userId <= 0 || $businessId <= 0) {
            abort(404);
        }

        $user = User::find($userId);
        $business = Business::find($businessId);
        if (!$user || !$business) {
            abort(404);
        }

        // Mark business-level subscription as unsubscribed.
        $service->unsubscribe($businessId, $userId, 'email_link');

        // Add suppression record (business scoped).
        EmailUnsubscribe::firstOrCreate(
            ['email' => (string) $user->email, 'business_id' => $businessId, 'reason' => 'user_unsubscribe'],
            ['user_id' => $userId, 'source' => 'email_link', 'unsubscribed_at' => now()]
        );

        return response()->view('unsubscribe', [
            'businessName' => $business->name,
            'email' => $user->email,
        ]);
    }
}

