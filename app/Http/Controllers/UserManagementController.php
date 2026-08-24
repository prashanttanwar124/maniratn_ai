<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display a listing of system users with roles
     */
    public function index(Request $request): Response
    {
        $query = User::with('roles')->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $roleFilter = $request->input('role');
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter);
            });
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? 'Viewer',
                'created_at' => $user->created_at->format('d M Y, h:i A'),
                'is_current_user' => $user->id === Auth::id(),
            ];
        });

        $roles = Role::pluck('name');

        $stats = [
            'total_users' => User::count(),
            'super_admins' => User::role('Super Admin')->count(),
            'operators' => User::role('AI Operator')->count(),
            'viewers' => User::role('Viewer')->count(),
        ];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'stats' => $stats,
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    /**
     * Store a newly created user in storage
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(6)],
            'role' => ['required', 'string', Rule::in(Role::pluck('name')->toArray())],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', "User '{$user->name}' created successfully with role '{$validated['role']}'.");
    }

    /**
     * Update the specified user in storage
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::min(6)],
            'role' => ['required', 'string', Rule::in(Role::pluck('name')->toArray())],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return back()->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Remove the specified user from storage
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Security Block: You cannot delete your own logged-in master account.');
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "User '{$userName}' has been deleted.");
    }
}
