<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Mail\EmployeeInvitation;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        $business = Auth::user()->business;

        // Get active employees through Employee model
        $employees = Employee::where('business_id', $business->id)
            ->where('is_active', true)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->user->id,
                    'name' => $employee->user->name,
                    'email' => $employee->user->email,
                    'role' => $employee->role,
                    'can_redeem' => $employee->can_redeem,
                    'can_view_analytics' => $employee->can_view_analytics,
                    'is_active' => $employee->is_active,
                    'created_at' => $employee->created_at,
                    'last_login_at' => $employee->user->last_login_at,
                ];
            });

        // Get pending invites
        $pendingInvites = EmployeeInvite::where('business_id', $business->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Business/Employees/Index', [
            'employees' => $employees,
            'pendingInvites' => $pendingInvites,
        ]);
    }

    public function create()
    {
        return Inertia::render('Business/Employees/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:manager,staff,employee',
        ]);

        $business = Auth::user()->business;
        $email = strtolower($request->email);

        // Check if already an employee
        // Note: users table does not have business_id; employees are tracked via employees table.
        $alreadyEmployee = Employee::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($alreadyEmployee) {
            return back()->withErrors(['email' => 'This person is already an employee.']);
        }

        // Check if invite already exists
        $existingInvite = EmployeeInvite::where('email', $email)
            ->where('business_id', $business->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvite) {
            $existingInvite->update([
                'role' => $request->role,
                'invited_by' => Auth::id(),
                'expires_at' => now()->addDays(7),
            ]);

            $inviteUrl = $existingInvite->url;

            // Resend email notification
            try {
                Mail::to($email)->send(new EmployeeInvitation($existingInvite, $inviteUrl));
            } catch (\Exception $e) {
                report($e);
            }

            return redirect()->route('business.employees.index')
                ->with('success', "Invitation resent to {$email}!")
                ->with('invite_url', $inviteUrl);
        }

        // Enforce plan limit for employees (hard limit; -1 means unlimited).
        // We count active employees + pending invites to avoid bypass via invites.
        $employeeLimit = $business->getLimit('employees');
        if ($employeeLimit !== -1) {
            $activeEmployeesCount = Employee::where('business_id', $business->id)
                ->where('is_active', true)
                ->count();

            $pendingInvitesCount = EmployeeInvite::where('business_id', $business->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->count();

            if (($activeEmployeesCount + $pendingInvitesCount) >= $employeeLimit) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'limit' => "Employee limit reached ({$employeeLimit}). Please upgrade your plan to add more employees.",
                    ]);
            }
        }

        // Create invite
        $invite = EmployeeInvite::createInvite(
            $business->id,
            $email,
            Auth::id(),
            $request->role
        );

        $inviteUrl = $invite->url;

        // Send email notification
        try {
            Mail::to($email)->send(new EmployeeInvitation($invite, $inviteUrl));
        } catch (\Exception $e) {
            // Log error but allow flow to continue so user can copy link manually
            report($e);
        }

        return redirect()->route('business.employees.index')
            ->with('success', "Invitation sent to {$email}!")
            ->with('invite_url', $inviteUrl); // Show URL in case email fails
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);
        
        return Inertia::render('Business/Employees/Show', [
            'employee' => $employee->load('user', 'redemptions'),
        ]);
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);
        
        return Inertia::render('Business/Employees/Edit', [
            'employee' => $employee->load('user'),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        
        $request->validate([
            'role' => 'required|in:manager,staff',
            'pin' => 'nullable|string|min:4|max:6',
            'can_redeem' => 'boolean',
            'can_view_analytics' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $employee->update([
            'role' => $request->role,
            'pin' => $request->pin ? Hash::make($request->pin) : $employee->pin,
            'can_redeem' => $request->can_redeem ?? true,
            'can_view_analytics' => $request->can_view_analytics ?? false,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('business.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $business = Auth::user()->business;

        // Ensure employee belongs to this business
        if ($employee->business_id !== $business->id) {
            abort(403);
        }

        // Remove employee from business (don't delete user, just deactivate)
        $employee->update([
            'is_active' => false,
        ]);

        return redirect()->route('business.employees.index')
            ->with('success', 'Employee removed successfully.');
    }

    /**
     * Resend an invitation
     */
    public function resendInvite(EmployeeInvite $invite)
    {
        $business = Auth::user()->business;

        if ($invite->business_id !== $business->id) {
            abort(403);
        }

        // Extend expiry
        $invite->update([
            'expires_at' => now()->addDays(7),
        ]);

        $inviteUrl = $invite->url;

        // Resend email
        try {
            Mail::to($invite->email)->send(new EmployeeInvitation($invite, $inviteUrl));
        } catch (\Exception $e) {
            report($e);
        }

        return back()
            ->with('success', "Invitation resent to {$invite->email}!")
            ->with('invite_url', $inviteUrl);
    }

    /**
     * Cancel a pending invitation
     */
    public function cancelInvite(EmployeeInvite $invite)
    {
        $business = Auth::user()->business;

        if ($invite->business_id !== $business->id) {
            abort(403);
        }

        $invite->delete();

        return back()->with('success', 'Invitation cancelled.');
    }
}

