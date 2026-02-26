<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GameService;
use App\Services\PromoClaimService;
use App\Services\ScanService;
use App\Support\OnboardingQr;
use App\Notifications\PortalOnboarding;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class CustomerRegisterController extends Controller
{
    protected GameService $gameService;
    protected PromoClaimService $promoClaimService;
    protected ScanService $scanService;

    public function __construct(GameService $gameService, PromoClaimService $promoClaimService, ScanService $scanService)
    {
        $this->gameService = $gameService;
        $this->promoClaimService = $promoClaimService;
        $this->scanService = $scanService;
    }

    /**
     * Display the customer registration view.
     */
    public function create(Request $request): Response
    {
        $redirectTo = $request->get('redirect_to');
        if (is_string($redirectTo) && str_starts_with($redirectTo, '/')) {
            if (preg_match('#^/promo/([A-Za-z0-9]{8})$#', $redirectTo, $m)) {
                $this->promoClaimService->rememberPendingPromo($request, $m[1]);
            }
        }

        return Inertia::render('Auth/CustomerRegister', [
            'from' => $request->get('from'), // Track where they came from (game, promo, etc)
            'redirectTo' => is_string($redirectTo) ? $redirectTo : null,
        ]);
    }

    /**
     * Handle customer registration.
     */
    public function store(Request $request): RedirectResponse
    {
        $preLoginSessionId = $request->session()->getId();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'redirect_to' => 'nullable|string',
            'from' => 'nullable|string|max:50',
        ]);

        // Create customer user
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $user->role = 'customer';
        $user->save();

        event(new Registered($user));

        Auth::login($user);

        // Associate any anonymous game plays with the new user account
        $associatedPlays = $this->gameService->associateAnonymousGamePlays($user);

        // Attach any anonymous scans (especially QRcade game scans) to the new user account
        $this->scanService->associateAnonymousScans($user, $preLoginSessionId);

        // If they came from a promo scan/email, claim it so it shows in Portal Scans + Saved
        $this->promoClaimService->claimPendingPromo($request, $user);

        // Track onboarding QR conversions (stored in user preferences for admin analytics)
        $from = $request->input('from');
        $onboardingCode = $request->session()->pull('onboarding_qr_code');
        if ($from === 'onboarding_qr' || $onboardingCode === OnboardingQr::CODE) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $prefs['onboarding_qr_code'] = OnboardingQr::CODE;
            $prefs['onboarding_qr_converted_at'] = now()->toIsoString();
            $user->preferences = $prefs;
            $user->save();
        }

        if (in_array($user->role, ['user', 'customer'], true)
            && !$user->notifications()->where('type', PortalOnboarding::class)->exists()) {
            $user->notify(new PortalOnboarding());
        }

        // Redirect back to where they came from, or portal
        $redirectTo = $request->get('redirect_to');
        if ($redirectTo && str_starts_with($redirectTo, '/')) {
            return redirect($redirectTo);
        }

        return redirect(route('portal.dashboard'));
    }
}
