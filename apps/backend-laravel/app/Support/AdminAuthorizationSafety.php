<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminAuthorizationSafety
{
    /**
     * @return list<string>
     */
    public function criticalPermissions(): array
    {
        return ['admin.access', 'users.manage', 'roles.manage'];
    }

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     */
    public function userKeepsCriticalAccess(array $roleNames, array $directPermissionNames): bool
    {
        return $this->hasCriticalPermissions($this->effectivePermissionsForUserAssignment($roleNames, $directPermissionNames));
    }

    /**
     * @param  list<string>  $newRolePermissionNames
     */
    public function roleChangeKeepsCriticalAccess(User $actor, Role $role, array $newRolePermissionNames): bool
    {
        if (! $actor->hasRole($role->name)) {
            return true;
        }

        $roleNames = $actor->roles()->pluck('name')->all();
        $directPermissionNames = $actor->permissions()->pluck('name')->all();
        $effectivePermissionNames = $directPermissionNames;

        foreach ($roleNames as $roleName) {
            if ($roleName === $role->name) {
                $effectivePermissionNames = array_merge($effectivePermissionNames, $newRolePermissionNames);

                continue;
            }

            $effectivePermissionNames = array_merge(
                $effectivePermissionNames,
                Role::findByName($roleName, 'web')->permissions()->pluck('name')->all(),
            );
        }

        return $this->hasCriticalPermissions($this->normalizeNames($effectivePermissionNames));
    }

    public function canDisableUser(User $target, bool $lock = false): bool
    {
        if (! $target->hasRole('super_admin')) {
            return true;
        }

        $query = User::role('super_admin')
            ->whereNull('disabled_at')
            ->orderBy('users.id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('users.id')
            ->contains(fn (int $id): bool => $id !== $target->id);
    }

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>  $directPermissionNames
     * @return list<string>
     */
    private function effectivePermissionsForUserAssignment(array $roleNames, array $directPermissionNames): array
    {
        $permissionNames = $directPermissionNames;

        foreach ($roleNames as $roleName) {
            $permissionNames = array_merge(
                $permissionNames,
                Role::findByName($roleName, 'web')->permissions()->pluck('name')->all(),
            );
        }

        return $this->normalizeNames($permissionNames);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function hasCriticalPermissions(array $permissionNames): bool
    {
        return array_diff($this->criticalPermissions(), $permissionNames) === [];
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function normalizeNames(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
