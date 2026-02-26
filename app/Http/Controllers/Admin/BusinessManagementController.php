<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class BusinessManagementController extends Controller
{
    public function create()
    {
        return Inertia::render('Admin/Businesses/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'business_name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:100',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->role = 'business';
            $user->email_verified_at = now(); // Admin-created: allow immediate login
            $user->save();

            $business = Business::create([
                'user_id' => $user->id,
                'name' => $validated['business_name'],
                'slug' => Str::slug($validated['business_name']) . '-' . Str::random(5),
                'type' => $validated['business_type'] ?? null,
                'trial_ends_at' => now()->addDays(14),
                'subscription_tier' => 'starter',
                'is_active' => true,
            ]);

            return $business;
        });

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', "Business \"{$business->name}\" created. Owner can log in at any time.");
    }

    public function index(Request $request)
    {
        $businesses = Business::query()
            ->with(['owner:id,name,email'])
            ->withCount('qrCodes')
            ->when($request->search, fn($q, $search) => 
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
            )
            ->when($request->status, fn($q, $status) => 
                $q->where('is_active', $status === 'active')
            )
            ->when($request->plan, fn($q, $plan) => 
                $q->where('subscription_tier', $plan)
            )
            ->when($request->subscription_status, fn($q, $subStatus) => 
                $q->where('subscription_status', $subStatus)
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        $testingAccountCount = Business::where('is_testing_account', true)->count();
        $cancelledCount = Business::where('subscription_status', 'canceled')->count();

        return Inertia::render('Admin/Businesses/Index', [
            'businesses' => $businesses,
            'filters' => $request->only(['search', 'status', 'plan', 'subscription_status']),
            'testingAccountCount' => $testingAccountCount,
            'cancelledCount' => $cancelledCount,
        ]);
    }

    public function show(Business $business)
    {
        $business->load(['owner', 'qrCodes', 'promotions']);
        $business->loadCount(['qrCodes', 'promotions', 'scans', 'redemptions']);

        return Inertia::render('Admin/Businesses/Show', [
            'business' => $business,
        ]);
    }

    public function edit(Business $business)
    {
        $business->load('owner');

        return Inertia::render('Admin/Businesses/Edit', [
            'business' => $business,
        ]);
    }

    public function update(Request $request, Business $business)
    {
        $request->merge([
            'subscription_status' => $request->subscription_status ?: null,
            'trial_ends_at' => $request->trial_ends_at ?: null,
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:businesses,slug,' . $business->id,
            'type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'is_active' => 'boolean',
            'is_testing_account' => 'boolean',
            'subscription_tier' => 'required|in:starter,growth,pro,enterprise',
            'subscription_status' => 'nullable|in:active,trialing,past_due,canceled,incomplete',
            'trial_ends_at' => 'nullable|date',
        ]);

        $business->update($validated);

        return redirect()->route('admin.businesses.show', $business)
            ->with('success', 'Business updated successfully.');
    }

    public function toggle(Business $business)
    {
        $business->update(['is_active' => !$business->is_active]);

        return back()->with('success', 
            $business->is_active ? 'Business activated' : 'Business deactivated'
        );
    }

    public function toggleTestingAccount(Business $business)
    {
        $business->update(['is_testing_account' => !$business->is_testing_account]);

        return back()->with('success', 
            $business->is_testing_account 
                ? 'Business marked as beta testing account' 
                : 'Beta testing account status removed'
        );
    }

    public function impersonate(Business $business)
    {
        if (!$business->owner) {
            return back()->with('error', 'Cannot impersonate: business has no owner.');
        }

        // Store admin ID for returning
        session(['impersonating_from' => auth()->id()]);

        // Login as business owner
        auth()->login($business->owner);

        return redirect()->route('business.dashboard')
            ->with('warning', "You are now viewing as {$business->name}");
    }
}

