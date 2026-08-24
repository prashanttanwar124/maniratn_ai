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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Display a listing of system users, roles, and permissions matching ERP layout.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['roles', 'permissions'])
            ->latest()
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => optional($user->created_at)?->format('Y-m-d H:i'),
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
                    'is_current_user' => $user->id === Auth::id(),
                ];
            });

        $roles = Role::query()
            ->with(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => str($role->name)->replace('_', ' ')->title()->toString(),
                    'permissions' => $role->permissions->pluck('name')->values(),
                    'users_count' => $role->users->count(),
                ];
            });

        $permissions = Permission::query()
            ->withCount(['roles', 'users'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'label' => str($permission->name)->replace('_', ' ')->title()->toString(),
                'value' => $permission->name,
                'roles_count' => $permission->roles_count,
                'users_count' => $permission->users_count,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'roleOptions' => $roles->map(fn (array $role) => [
                'label' => $role['label'],
                'value' => $role['name'],
            ])->values(),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(6)],
            'role' => ['required', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::firstOrCreate(['name' => $validated['role']]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$role->name]);
        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "User '{$user->name}' created successfully.");
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::min(6)],
            'role' => ['required', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);
        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Delete user
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Security Block: You cannot delete your own logged-in master account.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "User '{$name}' deleted successfully.");
    }

    /**
     * Store Role
     */
    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Role '{$role->name}' created successfully.");
    }

    /**
     * Update Role
     */
    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->name = $validated['name'];
        $role->save();
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Role '{$role->name}' updated successfully.");
    }

    /**
     * Delete Role
     */
    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', "Security Block: System role 'Super Admin' cannot be deleted.");
        }

        $roleName = $role->name;
        $role->delete();

        return back()->with('success', "Role '{$roleName}' deleted successfully.");
    }

    /**
     * Store Permission
     */
    public function storePermission(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ]);

        $permission = Permission::create(['name' => $validated['name'], 'guard_name' => 'web']);

        return back()->with('success', "Permission '{$permission->name}' created successfully.");
    }

    /**
     * Update Permission
     */
    public function updatePermission(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
        ]);

        $permission->name = $validated['name'];
        $permission->save();

        return back()->with('success', "Permission '{$permission->name}' updated successfully.");
    }

    /**
     * Delete Permission
     */
    public function destroyPermission(Permission $permission): RedirectResponse
    {
        $name = $permission->name;
        $permission->delete();

        return back()->with('success', "Permission '{$name}' deleted successfully.");
    }
}
