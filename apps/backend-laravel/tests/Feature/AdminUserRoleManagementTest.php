<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_receives_user_and_role_management_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Permission::where('name', 'users.view')->exists());
        $this->assertTrue(Permission::where('name', 'users.manage')->exists());
        $this->assertTrue(Permission::where('name', 'roles.manage')->exists());

        $superAdmin = User::factory()->create(['email' => 's32-super@example.test']);
        $superAdmin->assignRole('super_admin');

        $opsAdmin = User::factory()->create(['email' => 's32-ops@example.test']);
        $opsAdmin->assignRole('ops_admin');

        $this->assertTrue($superAdmin->hasPermissionTo('users.view'));
        $this->assertTrue($superAdmin->hasPermissionTo('users.manage'));
        $this->assertTrue($superAdmin->hasPermissionTo('roles.manage'));
        $this->assertFalse($opsAdmin->hasPermissionTo('users.view'));
        $this->assertFalse($opsAdmin->hasPermissionTo('users.manage'));
        $this->assertFalse($opsAdmin->hasPermissionTo('roles.manage'));
    }

    public function test_user_and_role_management_routes_are_protected_and_linked_from_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = $this->userWithRole('customer', 's32-customer@example.test');
        $adminWithoutUserView = $this->adminWithDirectPermissions('s32-admin-limited@example.test', ['admin.access']);
        $adminWithUserView = $this->adminWithDirectPermissions('s32-admin-view@example.test', ['admin.access', 'users.view']);

        $this->get('/admin/users')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/users')->assertForbidden();
        $this->actingAs($adminWithoutUserView)->get('/admin/users')->assertForbidden();
        $this->actingAs($adminWithoutUserView)->get('/admin/roles')->assertForbidden();

        $this->actingAs($adminWithoutUserView)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Users & Roles')
            ->assertDontSee('/admin/users', false);

        $this->actingAs($adminWithUserView)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Users & Roles')
            ->assertSee('/admin/users', false);

        $this->actingAs($adminWithUserView)->get('/admin/users')->assertOk();
        $this->actingAs($adminWithUserView)->get('/admin/roles')->assertOk();
        $this->actingAs($adminWithUserView)->post('/admin/users', [])->assertForbidden();
        $this->actingAs($adminWithUserView)->post('/admin/roles', [])->assertForbidden();
    }

    public function test_management_permissions_without_user_view_cannot_access_management_screens_or_mutations(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $target = $this->userWithRole('support', 's32-route-target@example.test');
        $userManagerWithoutView = $this->adminWithDirectPermissions('s32-user-manager-no-view@example.test', ['admin.access', 'users.manage']);
        $roleManagerWithoutView = $this->adminWithDirectPermissions('s32-role-manager-no-view@example.test', ['admin.access', 'roles.manage']);
        $superAdminRole = Role::findByName('super_admin');

        $this->actingAs($userManagerWithoutView)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($userManagerWithoutView)->get("/admin/users/{$target->id}/edit")->assertForbidden();
        $this->actingAs($userManagerWithoutView)->post('/admin/users', [
            'name' => 'Blocked User',
            'email' => 'blocked-user@example.test',
            'password' => 'blocked-password',
            'password_confirmation' => 'blocked-password',
            'roles' => ['support'],
            'permissions' => [],
        ])->assertForbidden();
        $this->actingAs($userManagerWithoutView)->put("/admin/users/{$target->id}", [
            'roles' => ['finance'],
            'permissions' => [],
        ])->assertForbidden();

        $this->actingAs($roleManagerWithoutView)->get('/admin/roles/create')->assertForbidden();
        $this->actingAs($roleManagerWithoutView)->get("/admin/roles/{$superAdminRole->id}/edit")->assertForbidden();
        $this->actingAs($roleManagerWithoutView)->post('/admin/roles', [
            'name' => 'blocked_role',
            'permissions' => ['admin.access'],
        ])->assertForbidden();
        $this->actingAs($roleManagerWithoutView)->put("/admin/roles/{$superAdminRole->id}", [
            'permissions' => ['admin.access'],
        ])->assertForbidden();
    }

    public function test_admin_can_create_user_with_roles_and_direct_permissions(): void
    {
        $admin = $this->superAdmin('s32-create-admin@example.test');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Support Operator',
                'email' => 'support-operator@example.test',
                'password' => 'operator-password',
                'password_confirmation' => 'operator-password',
                'roles' => ['support'],
                'permissions' => ['renewals.view'],
            ])
            ->assertRedirect('/admin/users');

        $operator = User::where('email', 'support-operator@example.test')->firstOrFail();

        $this->assertSame('Support Operator', $operator->name);
        $this->assertTrue(Hash::check('operator-password', $operator->password));
        $this->assertTrue($operator->hasRole('support'));
        $this->assertTrue($operator->hasDirectPermission('renewals.view'));

        $audit = AdminAuditLog::where('action', 'user_created')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame((string) $operator->id, $audit->auditable_id);
        $this->assertSame('support-operator@example.test', $audit->auditable_label);
        $this->assertSame(['support'], $audit->after['roles']);
        $this->assertSame(['renewals.view'], $audit->after['direct_permissions']);
        $this->assertArrayNotHasKey('password', $audit->after);
        $this->assertLogDoesNotContainSecrets($audit, ['operator-password']);

        $this->actingAs($admin)
            ->get('/admin/users?role=support')
            ->assertOk()
            ->assertSee('support-operator@example.test')
            ->assertSee('support');

        $this->actingAs($admin)
            ->get("/admin/users/{$operator->id}")
            ->assertOk()
            ->assertSee('support-operator@example.test')
            ->assertSee('renewals.view');
    }

    public function test_admin_can_update_user_roles_and_direct_permissions_with_audit(): void
    {
        $admin = $this->superAdmin('s32-update-admin@example.test');
        $operator = $this->userWithRole('customer', 'role-target@example.test');
        $operator->givePermissionTo('products.view');

        $this->actingAs($admin)
            ->put("/admin/users/{$operator->id}", [
                'roles' => ['finance'],
                'permissions' => ['renewals.view'],
            ])
            ->assertRedirect("/admin/users/{$operator->id}");

        $operator = User::findOrFail($operator->id);
        $this->assertTrue($operator->hasRole('finance'));
        $this->assertFalse($operator->hasRole('customer'));
        $this->assertTrue($operator->hasDirectPermission('renewals.view'));
        $this->assertFalse($operator->hasDirectPermission('products.view'));

        $audit = AdminAuditLog::where('action', 'user_roles_updated')->firstOrFail();
        $this->assertSame((string) $operator->id, $audit->auditable_id);
        $this->assertSame(['customer'], $audit->before['roles']);
        $this->assertSame(['products.view'], $audit->before['direct_permissions']);
        $this->assertSame(['finance'], $audit->after['roles']);
        $this->assertSame(['renewals.view'], $audit->after['direct_permissions']);
    }

    public function test_admin_can_create_and_update_custom_role_permissions_with_audit(): void
    {
        $admin = $this->superAdmin('s32-role-admin@example.test');

        $this->actingAs($admin)
            ->post('/admin/roles', [
                'name' => 'renewal_operator',
                'permissions' => ['admin.access', 'renewals.view', 'renewals.manage'],
            ])
            ->assertRedirect('/admin/roles');

        $role = Role::where('name', 'renewal_operator')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('admin.access'));
        $this->assertTrue($role->hasPermissionTo('renewals.manage'));

        $created = AdminAuditLog::where('action', 'role_created')->firstOrFail();
        $this->assertSame((string) $role->id, $created->auditable_id);
        $this->assertSame(['admin.access', 'renewals.manage', 'renewals.view'], $created->after['permissions']);

        $this->actingAs($admin)
            ->put("/admin/roles/{$role->id}", [
                'permissions' => ['admin.access', 'renewals.view'],
            ])
            ->assertRedirect('/admin/roles');

        $role = Role::findByName('renewal_operator');
        $this->assertTrue($role->hasPermissionTo('admin.access'));
        $this->assertTrue($role->hasPermissionTo('renewals.view'));
        $this->assertFalse($role->hasPermissionTo('renewals.manage'));

        $updated = AdminAuditLog::where('action', 'role_permissions_updated')->firstOrFail();
        $this->assertSame(['admin.access', 'renewals.manage', 'renewals.view'], $updated->before['permissions']);
        $this->assertSame(['admin.access', 'renewals.view'], $updated->after['permissions']);

        $this->actingAs($admin)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee('renewal_operator')
            ->assertSee('renewals.view');
    }

    public function test_self_user_update_cannot_remove_critical_management_access(): void
    {
        $admin = $this->superAdmin('s32-self-user-admin@example.test');

        $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}/edit")
            ->put("/admin/users/{$admin->id}", [
                'roles' => ['customer'],
                'permissions' => [],
            ])
            ->assertRedirect("/admin/users/{$admin->id}/edit")
            ->assertSessionHasErrors('authorization');

        $admin = User::findOrFail($admin->id);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->hasPermissionTo('admin.access'));
        $this->assertTrue($admin->hasPermissionTo('users.manage'));
        $this->assertTrue($admin->hasPermissionTo('roles.manage'));
        $this->assertSame(0, AdminAuditLog::where('action', 'user_roles_updated')->count());
    }

    public function test_user_authorization_update_rolls_back_when_audit_fails(): void
    {
        $admin = $this->superAdmin('s32-user-audit-failure-admin@example.test');
        $operator = $this->userWithRole('customer', 'rollback-target@example.test');
        $operator->givePermissionTo('products.view');
        $this->bindFailingAuditLogger();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->put("/admin/users/{$operator->id}", [
                'roles' => ['finance'],
                'permissions' => ['renewals.view'],
            ]);
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failed', $exception->getMessage());
        }

        $operator = User::findOrFail($operator->id);
        $this->assertTrue($operator->hasRole('customer'));
        $this->assertFalse($operator->hasRole('finance'));
        $this->assertTrue($operator->hasDirectPermission('products.view'));
        $this->assertFalse($operator->hasDirectPermission('renewals.view'));
        $this->assertSame(0, AdminAuditLog::where('action', 'user_roles_updated')->count());
    }

    public function test_self_assigned_role_update_cannot_remove_critical_management_access(): void
    {
        $admin = $this->superAdmin('s32-self-role-admin@example.test');
        $role = Role::findByName('super_admin');

        $this->actingAs($admin)
            ->from("/admin/roles/{$role->id}/edit")
            ->put("/admin/roles/{$role->id}", [
                'permissions' => ['admin.access'],
            ])
            ->assertRedirect("/admin/roles/{$role->id}/edit")
            ->assertSessionHasErrors('authorization');

        $role = Role::findByName('super_admin');
        $this->assertTrue($role->hasPermissionTo('users.manage'));
        $this->assertTrue($role->hasPermissionTo('roles.manage'));
        $this->assertSame(0, AdminAuditLog::where('action', 'role_permissions_updated')->count());
    }

    public function test_role_permission_update_rolls_back_when_audit_fails(): void
    {
        $admin = $this->superAdmin('s32-role-audit-failure-admin@example.test');
        $role = Role::create(['name' => 'rollback_role', 'guard_name' => 'web']);
        $role->syncPermissions(['admin.access', 'renewals.view']);
        $this->bindFailingAuditLogger();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
                'permissions' => ['admin.access', 'renewals.manage'],
            ]);
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failed', $exception->getMessage());
        }

        $role = Role::findByName('rollback_role');
        $this->assertTrue($role->hasPermissionTo('admin.access'));
        $this->assertTrue($role->hasPermissionTo('renewals.view'));
        $this->assertFalse($role->hasPermissionTo('renewals.manage'));
        $this->assertSame(0, AdminAuditLog::where('action', 'role_permissions_updated')->count());
    }

    public function test_non_web_guard_roles_are_not_editable_from_admin_role_routes(): void
    {
        $admin = $this->superAdmin('s32-non-web-role-admin@example.test');
        $role = Role::create(['name' => 'api_operator', 'guard_name' => 'api']);

        $this->actingAs($admin)->get("/admin/roles/{$role->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'permissions' => ['admin.access'],
        ])->assertNotFound();
    }

    private function superAdmin(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return $this->userWithRole('super_admin', $email);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function adminWithDirectPermissions(string $email, array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['email' => $email]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function bindFailingAuditLogger(): void
    {
        $this->app->instance(AuditLogger::class, new class extends AuditLogger
        {
            /**
             * @param  array<string, mixed>  $before
             * @param  array<string, mixed>  $after
             * @param  array<string, mixed>  $metadata
             */
            public function record(
                ?User $actor,
                string $action,
                Model $auditable,
                array $before = [],
                array $after = [],
                array $metadata = [],
                ?Request $request = null,
                ?string $label = null,
            ): AdminAuditLog {
                throw new RuntimeException('audit failed');
            }
        });
    }

    /**
     * @param  list<string>  $secrets
     */
    private function assertLogDoesNotContainSecrets(AdminAuditLog $auditLog, array $secrets): void
    {
        $encoded = json_encode($auditLog->toArray(), JSON_THROW_ON_ERROR);

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }
}
