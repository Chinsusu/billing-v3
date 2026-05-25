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
            'renewals.view',
            'renewals.manage',
            'provisioning_jobs.view',
            'wallets.adjust',
            'notifications.manage',
            'audit_logs.view',
            'users.view',
            'users.manage',
            'roles.manage',
            'support_tickets.view',
            'support_tickets.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $superAdmin = Role::findOrCreate('super_admin');
        $superAdmin->syncPermissions($permissions);

        Role::findOrCreate('ops_admin')->syncPermissions(['admin.access', 'products.view', 'products.create', 'products.update', 'invoices.view', 'invoices.create', 'payment_events.view', 'provisioning_provider_accounts.manage', 'orders.view', 'services.view', 'renewals.view', 'renewals.manage', 'provisioning_jobs.view', 'notifications.manage', 'support_tickets.view', 'support_tickets.manage']);
        Role::findOrCreate('support')->syncPermissions(['admin.access', 'products.view', 'customers.view', 'support_tickets.view', 'support_tickets.manage']);
        Role::findOrCreate('finance')->syncPermissions(['admin.access', 'invoices.view', 'invoices.create', 'payment_events.view', 'bank_integrations.manage', 'customers.view', 'wallets.adjust', 'orders.view']);
        Role::findOrCreate('customer');
        Role::findOrCreate('reseller');
    }
}
