<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        // Check for referral code in URL or session
        $referralCode = $request->get('ref') ?? session('referral_code');
        $referrer = null;

        if ($referralCode) {
            $referrer = User::where('referral_code', $referralCode)->first();
            // Store in session for form submission
            session(['referral_code' => $referralCode]);
        }

        return Inertia::render('Auth/Register', [
            'referralCode' => $referralCode,
            'referrerName' => $referrer?->name,
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
        ]);

        // Check for referral
        $referralCode = $request->get('referral_code') ?? session('referral_code');
        $referrer = null;
        
        if ($referralCode) {
            $referrer = User::where('referral_code', $referralCode)->first();

            // Prevent self-referral fraud: if the referrer's email matches
            // the registering user's email, ignore the referral.
            if ($referrer && mb_strtolower($referrer->email) === mb_strtolower($request->email)) {
                $referrer = null;
            }
        }

        // Wrap all creation in a transaction so partial failures don't leave orphaned records
        $user = DB::transaction(function () use ($request, $referrer, $referralCode) {
            // Create user (role set explicitly to prevent mass-assignment privilege escalation)
            $user = new User([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $user->role = 'business';
            $user->referred_by_user_id = $referrer?->id;
            $user->save();

            // Create business
            $business = Business::create([
                'user_id' => $user->id,
                'name' => $request->business_name,
                'slug' => Str::slug($request->business_name) . '-' . Str::random(5),
                'type' => $request->business_type,
                'trial_ends_at' => now()->addDays(14),
                'subscription_tier' => 'starter',
            ]);

            // Update user with business_id
            $user->update(['business_id' => $business->id]);

            // Create referral record if referred
            if ($referrer) {
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'business_id' => $business->id,
                    'business_user_id' => $user->id,
                    'referral_code' => $referralCode,
                    'commission_rate' => 10.00, // 10% default
                    'status' => 'active',
                ]);

                // Clear session
                session()->forget('referral_code');
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('business.dashboard'));
    }
}

