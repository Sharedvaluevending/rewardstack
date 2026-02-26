<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class CustomerCodeService
{
    /**
     * Ensure the user has a stable "customer code" stored in preferences.
     * Used for account-only punch card attribution without collecting phone numbers.
     */
    public function getOrCreate(User $user): string
    {
        $prefs = is_array($user->preferences) ? $user->preferences : [];
        $existing = $prefs['customer_code'] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        do {
            // Easy to read/enter in-store. Prefix to reduce confusion with QR codes.
            $code = 'C' . strtoupper(Str::random(8));
            $exists = User::query()
                ->where('role', 'customer')
                ->where('preferences->customer_code', $code)
                ->exists();
        } while ($exists);

        $prefs['customer_code'] = $code;
        $user->update(['preferences' => $prefs]);

        return $code;
    }
}


