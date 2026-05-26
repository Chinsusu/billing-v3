<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Support\AdminAuthorizationSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::where('guard_name', 'web')->with(['permissions'])->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
        $permissionNames = $this->normalizedPermissionNames($validated['permissions'] ?? []);

        DB::transaction(function () use ($auditLogger, $permissionNames, $request, $validated): void {
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
        });

        return redirect('/admin/roles')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        $this->abortUnlessWebRole($role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
            'assignedPermissions' => $this->permissionNames($role),
        ]);
    }

    public function update(
        Role $role,
        Request $request,
        AuditLogger $auditLogger,
        AdminAuthorizationSafety $authorizationSafety,
    ): RedirectResponse {
        $this->abortUnlessWebRole($role);

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
        $permissionNames = $this->normalizedPermissionNames($validated['permissions'] ?? []);
        $before = ['permissions' => $this->permissionNames($role)];

        if (! $authorizationSafety->roleChangeKeepsCriticalAccess($request->user(), $role, $permissionNames)) {
            return back()
                ->withErrors(['authorization' => 'You cannot remove your own admin and role management access.'])
                ->withInput();
        }

        DB::transaction(function () use ($auditLogger, $before, $permissionNames, $request, $role): void {
            $role->syncPermissions($permissionNames);
            $after = ['permissions' => $this->permissionNames($role->refresh())];

            [$beforeChanges, $afterChanges] = $auditLogger->diff($before, $after);
            if ($beforeChanges !== [] || $afterChanges !== []) {
                $auditLogger->record($request->user(), 'role_permissions_updated', $role, $beforeChanges, $afterChanges, [], $request, $role->name);
            }
        });

        return redirect('/admin/roles')->with('status', 'Role permissions updated.');
    }

    private function abortUnlessWebRole(Role $role): void
    {
        abort_unless($role->guard_name === 'web', 404);
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
