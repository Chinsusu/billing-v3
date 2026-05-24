<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.access',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('super_admin');
        $superAdmin->syncPermissions($permissions);

        Role::findOrCreate('ops_admin')->syncPermissions(['admin.access', 'products.view', 'products.create', 'products.update']);
        Role::findOrCreate('support')->syncPermissions(['admin.access', 'products.view']);
        Role::findOrCreate('finance')->syncPermissions(['admin.access']);
        Role::findOrCreate('customer');
        Role::findOrCreate('reseller');
    }
}
