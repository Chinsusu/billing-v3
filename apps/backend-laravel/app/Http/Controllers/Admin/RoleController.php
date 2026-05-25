<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Support\AdminAuthorizationSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::with(['permissions'])->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('roles', 'name')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $permissionNames = $this->normalizedPermissionNames($validated['permissions'] ?? []);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($permissionNames);

        $auditLogger->record(
            $request->user(),
            'role_created',
            $role,
            [],
            ['name' => $role->name, 'permissions' => $this->permissionNames($role)],
            [],
            $request,
            $role->name,
        );

        return redirect('/admin/roles')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::orderBy('name')->get(),
            'assignedPermissions' => $this->permissionNames($role),
        ]);
    }

    public function update(
        Role $role,
        Request $request,
        AuditLogger $auditLogger,
        AdminAuthorizationSafety $authorizationSafety,
    ): RedirectResponse {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $permissionNames = $this->normalizedPermissionNames($validated['permissions'] ?? []);
        $before = ['permissions' => $this->permissionNames($role)];

        if (! $authorizationSafety->roleChangeKeepsCriticalAccess($request->user(), $role, $permissionNames)) {
            return back()
                ->withErrors(['authorization' => 'You cannot remove your own admin and role management access.'])
                ->withInput();
        }

        $role->syncPermissions($permissionNames);
        $after = ['permissions' => $this->permissionNames($role->refresh())];

        [$beforeChanges, $afterChanges] = $auditLogger->diff($before, $after);
        if ($beforeChanges !== [] || $afterChanges !== []) {
            $auditLogger->record($request->user(), 'role_permissions_updated', $role, $beforeChanges, $afterChanges, [], $request, $role->name);
        }

        return redirect('/admin/roles')->with('status', 'Role permissions updated.');
    }

    /**
     * @return list<string>
     */
    private function permissionNames(Role $role): array
    {
        return $this->normalizedPermissionNames($role->permissions()->pluck('name')->all());
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @return list<string>
     */
    private function normalizedPermissionNames(array $permissionNames): array
    {
        $permissionNames = array_values(array_unique($permissionNames));
        sort($permissionNames);

        return $permissionNames;
    }
}
