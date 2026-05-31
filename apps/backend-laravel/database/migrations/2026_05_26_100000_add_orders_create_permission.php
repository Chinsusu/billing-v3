<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('orders.create', 'web');

        foreach (['super_admin', 'finance'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::where('name', 'orders.create')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        foreach (['super_admin', 'finance'] as $roleName) {
            Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
