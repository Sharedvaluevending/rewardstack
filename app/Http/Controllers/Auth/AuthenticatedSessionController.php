<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GameService;
use App\Services\PromoClaimService;
use App\Services\ScanService;
use App\Notifications\PortalOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
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
     * Display the login view.
     */
    public function create(): Response
    {
        $redirectTo = request()->get('redirect_to');
        if (is_string($redirectTo) && str_starts_with($redirectTo, '/')) {
            // Set intended URL so redirect()->intended works for public pages too
            request()->session()->put('url.intended', $redirectTo);

            // If they're coming from a promo link, remember it for reliable portal saving
            if (preg_match('#^/promo/([A-Za-z0-9]{8})$#', $redirectTo, $m)) {
                $this->promoClaimService->rememberPendingPromo(request(), $m[1]);
            }
        }

        return Inertia::render('Auth/Login', [
            'redirectTo' => is_string($redirectTo) ? $redirectTo : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        // Capture the pre-login session id so we can attach any scans recorded while anonymous.
        $preLoginSessionId = $request->session()->getId();

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $redirectTo = $validated['redirect_to'] ?? null;
        if (is_string($redirectTo) && str_starts_with($redirectTo, '/')) {
            $request->session()->put('url.intended', $redirectTo);

            if (preg_match('#^/promo/([A-Za-z0-9]{8})$#', $redirectTo, $m)) {
                $this->promoClaimService->rememberPendingPromo($request, $m[1]);
            }
        }

        // Only pass authentication credentials to Auth::attempt (never include redirect_to).
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Associate any anonymous game plays with the user account
        $this->gameService->associateAnonymousGamePlays($user);

        // Attach any anonymous scans (especially QRcade game scans) to the user account
        // so Portal -> My Scans & Promotions shows what they just scanned/played.
        if (in_array($user->role, ['user', 'customer'], true)) {
            $this->scanService->associateAnonymousScans($user, $preLoginSessionId);
        }

        // If they came from a promo scan/email, claim it so it shows in Portal Scans + Saved
        $this->promoClaimService->claimPendingPromo($request, $user);

        if (in_array($user->role, ['user', 'customer'], true)
            && !$user->notifications()->where('type', PortalOnboarding::class)->exists()) {
            $user->notify(new PortalOnboarding());
        }

        // Redirect based on role
        return $this->redirectBasedOnRole($user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Redirect user based on their role.
     */
    protected function redirectBasedOnRole(User $user): RedirectResponse
    {
        $intended = $this->safeIntendedForRole($user);
        if ($intended) {
            return redirect($intended);
        }

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'business' => redirect()->route('business.dashboard'),
            'employee' => redirect()->route('employee.redeem'),
            'user', 'customer' => redirect()->route('portal.dashboard'),
            default => redirect()->route('portal.dashboard'),
        };
    }

    protected function safeIntendedForRole(User $user): ?string
    {
        $intended = session('url.intended');
        if (!is_string($intended) || $intended === '' || !str_starts_with($intended, '/')) {
            return null;
        }

        if ($user->role === 'employee' && str_starts_with($intended, '/portal')) {
            return null;
        }

        if (in_array($user->role, ['user', 'customer'], true) && str_starts_with($intended, '/employee')) {
            return null;
        }

        if (in_array($user->role, ['admin', 'business'], true) && str_starts_with($intended, '/portal')) {
            return null;
        }

        if (in_array($user->role, ['admin', 'business'], true) && str_starts_with($intended, '/employee')) {
            return null;
        }

        return $intended;
    }
}

