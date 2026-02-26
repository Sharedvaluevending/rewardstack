<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeInviteController extends Controller
{
    /**
     * Display the employee invitation acceptance view.
     */
    public function show(string $token): Response|RedirectResponse
    {
        $invite = EmployeeInvite::where('token', $token)
            ->where('expires_at', '>', now())
            ->whereNull('accepted_at')
            ->with('business')
            ->first();

        if (!$invite) {
            return Inertia::render('Auth/InviteExpired');
        }

        return Inertia::render('Auth/AcceptInvite', [
            'invite' => [
                'token' => $invite->token,
                'email' => $invite->email,
                'business_name' => $invite->business->name,
                'business_logo' => $invite->business->logo_url,
                'role' => $invite->role,
            ],
        ]);
    }

    /**
     * Handle employee invitation acceptance.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = EmployeeInvite::where('token', $token)
            ->where('expires_at', '>', now())
            ->whereNull('accepted_at')
            ->first();

        if (!$invite) {
            return redirect()->route('login')->withErrors([
                'email' => 'This invitation has expired or already been used.',
            ]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Check if user already exists
        $user = User::where('email', $invite->email)->first();

        if ($user) {
            // Prevent downgrading admin or business owner accounts to employee.
            // These roles have higher privileges and should not be overwritten by an invite.
            if (in_array($user->role, ['admin', 'business'], true)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'This account already has a higher-level role and cannot be converted to an employee. Please use a different email address.',
                ]);
            }

            // For customer/user accounts, upgrade to employee role
            if (!in_array($user->role, ['employee'], true)) {
                $user->forceFill(['role' => 'employee'])->save();
            }
        } else {
            // Create new employee user
            $user = new User([
                'name' => $request->name,
                'email' => $invite->email,
                'password' => Hash::make($request->password),
            ]);
            $user->role = 'employee';
            $user->save();

            event(new Registered($user));
        }

        // Ensure an employee record exists for this business
        Employee::updateOrCreate(
            [
                'business_id' => (int) $invite->business_id,
                'user_id' => (int) $user->id,
            ],
            [
                'role' => (string) ($invite->role ?: 'employee'),
                'is_active' => true,
                'can_redeem' => true,
                // Managers can view analytics by default; others cannot.
                'can_view_analytics' => ($invite->role === 'manager'),
            ]
        );

        // Mark invite as accepted
        $invite->update([
            'accepted_at' => now(),
            'user_id' => $user->id,
        ]);

        Auth::login($user);

        return redirect(route('employee.redeem'))->with('success', 
            "Welcome to {$invite->business->name}! You're now set up as an employee."
        );
    }
}
