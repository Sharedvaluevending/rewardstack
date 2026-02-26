<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    public function create()
    {
        $businesses = Business::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Users/Create', [
            'businesses' => $businesses,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,business,employee,user,customer',
        ];

        if ($request->role === 'business') {
            $rules['business_name'] = 'required|string|max:255';
            $rules['business_type'] = 'nullable|string|max:100';
        }

        if ($request->role === 'employee') {
            $rules['business_id'] = 'required|exists:businesses,id';
        }

        $validated = $request->validate($rules);

        $user = DB::transaction(function () use ($validated) {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->forceFill(['role' => $validated['role']]);
            $user->email_verified_at = now();
            $user->save();

            if ($validated['role'] === 'business') {
                $business = Business::create([
                    'user_id' => $user->id,
                    'name' => $validated['business_name'],
                    'slug' => Str::slug($validated['business_name']) . '-' . Str::random(5),
                    'type' => $validated['business_type'] ?? null,
                    'trial_ends_at' => now()->addDays(14),
                    'subscription_tier' => 'starter',
                    'is_active' => true,
                ]);
            }

            if ($validated['role'] === 'employee') {
                Employee::create([
                    'business_id' => $validated['business_id'],
                    'user_id' => $user->id,
                    'is_active' => true,
                    'can_redeem' => true,
                    'can_view_analytics' => false,
                ]);
            }

            return $user;
        });

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User \"{$user->name}\" created successfully.");
    }

    public function index(Request $request)
    {
        $users = User::query()
            ->with('business:id,name')
            ->when($request->search, fn($q, $search) => 
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($request->role, fn($q, $role) => 
                $q->where('role', $role)
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function show(User $user)
    {
        $user->load([
            'business',
            'gamePlays' => fn($q) => $q->latest()->limit(10),
            'gameRewards' => fn($q) => $q->latest()->limit(10),
        ]);

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
        ]);
    }

    public function toggle(User $user)
    {
        // Toggle email_verified_at as a way to disable/enable users
        if ($user->email_verified_at) {
            $user->update(['email_verified_at' => null]);
            $message = 'User disabled';
        } else {
            $user->update(['email_verified_at' => now()]);
            $message = 'User enabled';
        }

        return back()->with('success', $message);
    }
}

