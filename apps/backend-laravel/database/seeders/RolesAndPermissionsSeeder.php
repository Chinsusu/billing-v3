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
            'invoices.view',
            'invoices.create',
            'payment_events.view',
            'bank_integrations.manage',
            'customers.view',
            'provisioning_provider_accounts.manage',
            'orders.view',
            'services.view',
            'provisioning_jobs.view',
            'wallets.adjust',
            'notifications.manage',
            'audit_logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('super_admin');
        $superAdmin->syncPermissions($permissions);

        Role::findOrCreate('ops_admin')->syncPermissions(['admin.access', 'products.view', 'products.create', 'products.update', 'invoices.view', 'invoices.create', 'payment_events.view', 'provisioning_provider_accounts.manage', 'orders.view', 'services.view', 'provisioning_jobs.view', 'notifications.manage']);
        Role::findOrCreate('support')->syncPermissions(['admin.access', 'products.view', 'customers.view']);
        Role::findOrCreate('finance')->syncPermissions(['admin.access', 'invoices.view', 'invoices.create', 'payment_events.view', 'bank_integrations.manage', 'customers.view', 'wallets.adjust', 'orders.view']);
        Role::findOrCreate('customer');
        Role::findOrCreate('reseller');
    }
}
