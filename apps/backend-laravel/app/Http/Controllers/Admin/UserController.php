<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\AdminAuthorizationSafety;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));

        $users = User::query()
            ->with(['roles', 'permissions'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function ($query) use ($role): void {
                $query->role($role);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['roles', 'permissions']);

        return view('admin.users.show', [
            'managedUser' => $user,
            'roles' => $this->roleNames($user),
            'directPermissions' => $this->directPermissionNames($user),
            'effectivePermissions' => $this->effectivePermissionNames($user),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', $this->assignmentCatalog());
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $this->validateUserPayload($request, true);
        $roleNames = $this->normalizedInputNames($validated['roles']);
        $permissionNames = $this->normalizedInputNames($validated['permissions'] ?? []);

        DB::transaction(function () use ($auditLogger, $permissionNames, $request, $roleNames, $validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->syncRoles($roleNames);
            $user->syncPermissions($permissionNames);

            $after = $this->authorizationSnapshot($user->refresh());
            $auditLogger->record(
                $request->user(),
                'user_created',
                $user,
                [],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $after['roles'],
                    'direct_permissions' => $after['direct_permissions'],
                ],
                [],
                $request,
                $user->email,
            );
        });

        return redirect('/admin/users')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', array_merge($this->assignmentCatalog(), [
            'managedUser' => $user->load(['roles', 'permissions']),
            'assignedRoles' => $this->roleNames($user),
            'assignedPermissions' => $this->directPermissionNames($user),
        ]));
    }

    public function update(
        User $user,
        Request $request,
        AuditLogger $auditLogger,
        AdminAuthorizationSafety $authorizationSafety,
    ): RedirectResponse {
        $validated = $this->validateUserPayload($request, false);
        $roleNames = $this->normalizedInputNames($validated['roles']);
        $permissionNames = $this->normalizedInputNames($validated['permissions'] ?? []);
        $before = $this->authorizationSnapshot($user);

        if ($request->user()?->is($user) && ! $authorizationSafety->userKeepsCriticalAccess($roleNames, $permissionNames)) {
            return back()
                ->withErrors(['authorization' => 'You cannot remove your own admin and role management access.'])
                ->withInput();
        }

        DB::transaction(function () use ($auditLogger, $before, $permissionNames, $request, $roleNames, $user): void {
            $user->syncRoles($roleNames);
            $user->syncPermissions($permissionNames);
            $after = $this->authorizationSnapshot($user->refresh());

            [$beforeChanges, $afterChanges] = $auditLogger->diff($before, $after);
            if ($beforeChanges !== [] || $afterChanges !== []) {
                $auditLogger->record($request->user(), 'user_roles_updated', $user, $beforeChanges, $afterChanges, [], $request, $user->email);
            }
        });

        return redirect("/admin/users/{$user->id}")->with('status', 'User authorization updated.');
    }

    /**
     * @return array{roles: Collection<int, Role>, permissions: Collection<int, Permission>}
     */
    private function assignmentCatalog(): array
    {
        return [
            'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUserPayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', 'unique:users,email'],
            'password' => [$creating ? 'required' : 'sometimes', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
    }

    /**
     * @return array{roles: list<string>, direct_permissions: list<string>}
     */
    private function authorizationSnapshot(User $user): array
    {
        return [
            'roles' => $this->roleNames($user),
            'direct_permissions' => $this->directPermissionNames($user),
        ];
    }

    /**
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        return $this->normalizedInputNames($user->roles()->pluck('name')->all());
    }

    /**
     * @return list<string>
     */
    private function directPermissionNames(User $user): array
    {
        return $this->normalizedInputNames($user->permissions()->pluck('name')->all());
    }

    /**
     * @return list<string>
     */
    private function effectivePermissionNames(User $user): array
    {
        return $this->normalizedInputNames($user->getAllPermissions()->pluck('name')->all());
    }

    /**
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private function normalizedInputNames(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
