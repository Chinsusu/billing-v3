<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperatorAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_security_columns_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'disabled_at',
            'disabled_reason',
            'force_password_reset_at',
            'invited_at',
            'last_password_reset_at',
            'last_login_at',
            'last_login_ip',
            'last_login_user_agent',
        ]));
    }

    public function test_disabled_user_cannot_login_and_attempt_is_audited(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-disabled-login@example.test');
        $user->forceFill([
            'password' => Hash::make('Password123!'),
            'disabled_at' => now(),
            'disabled_reason' => 'Suspicious activity',
        ])->save();

        $this->post('/login', [
            'email' => 's33-disabled-login@example.test',
            'password' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $audit = AdminAuditLog::where('action', 'user_login_blocked_disabled')->firstOrFail();
        $this->assertSame((string) $user->id, $audit->auditable_id);
        $this->assertSame('s33-disabled-login@example.test', $audit->auditable_label);
        $this->assertSame('Suspicious activity', $audit->metadata['disabled_reason']);
    }

    public function test_disabled_authenticated_session_is_logged_out(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-disabled-session@example.test');
        $user->forceFill([
            'disabled_at' => now(),
            'disabled_reason' => 'Operator disabled account',
        ])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Account disabled.');

        $this->assertGuest();
    }

    public function test_successful_login_updates_activity_and_is_audited(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-login-activity@example.test');
        $user->forceFill(['password' => Hash::make('Password123!')])->save();

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'S33 Browser',
        ])->post('/login', [
            'email' => 's33-login-activity@example.test',
            'password' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $user = User::findOrFail($user->id);
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('203.0.113.10', $user->last_login_ip);
        $this->assertSame('S33 Browser', $user->last_login_user_agent);

        $audit = AdminAuditLog::where('action', 'user_login_succeeded')->firstOrFail();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame((string) $user->id, $audit->auditable_id);
        $this->assertSame('203.0.113.10', $audit->metadata['ip_address']);
    }

    public function test_security_routes_require_user_view_and_manage_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $target = $this->customer('s33-protected-target@example.test');
        $viewer = $this->adminWithDirectPermissions('s33-view-only@example.test', ['admin.access', 'users.view']);

        $this->post("/admin/users/{$target->id}/security/force-password-reset")->assertRedirect('/login');

        $this->actingAs($viewer)
            ->post("/admin/users/{$target->id}/security/force-password-reset")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post("/admin/users/{$target->id}/security/send-reset-link")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post("/admin/users/{$target->id}/security/disable", ['reason' => 'No access'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post("/admin/users/{$target->id}/security/enable")
            ->assertForbidden();
    }

    public function test_admin_can_force_and_clear_password_reset(): void
    {
        $admin = $this->superAdmin('s33-force-admin@example.test');
        $target = $this->customer('s33-force-target@example.test');

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/security/force-password-reset")
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNotNull($target->force_password_reset_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_force_password_reset_required')->count());

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/security/clear-force-password-reset")
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNull($target->force_password_reset_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_force_password_reset_cleared')->count());
    }

    public function test_admin_can_disable_and_enable_user_with_audit_and_ui_status(): void
    {
        $admin = $this->superAdmin('s33-disable-admin@example.test');
        $target = $this->customer('s33-disable-target@example.test');

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/security/disable", ['reason' => 'Fraud review'])
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNotNull($target->disabled_at);
        $this->assertSame('Fraud review', $target->disabled_reason);

        $disabledAudit = AdminAuditLog::where('action', 'user_disabled')->firstOrFail();
        $this->assertSame('Fraud review', $disabledAudit->after['disabled_reason']);

        $this->actingAs($admin)
            ->get("/admin/users/{$target->id}")
            ->assertOk()
            ->assertSee('Security')
            ->assertSee('Disabled')
            ->assertSee('Fraud review');

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/security/enable")
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNull($target->disabled_at);
        $this->assertNull($target->disabled_reason);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_enabled')->count());
    }

    public function test_admin_cannot_disable_self_or_last_enabled_super_admin(): void
    {
        $admin = $this->superAdmin('s33-self-disable@example.test');
        $manager = $this->adminWithDirectPermissions('s33-security-manager@example.test', ['admin.access', 'users.view', 'users.manage']);

        $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}")
            ->post("/admin/users/{$admin->id}/security/disable", ['reason' => 'Self disable'])
            ->assertRedirect("/admin/users/{$admin->id}")
            ->assertSessionHasErrors('security');

        $admin = User::findOrFail($admin->id);
        $this->assertNull($admin->disabled_at);

        $this->actingAs($manager)
            ->from("/admin/users/{$admin->id}")
            ->post("/admin/users/{$admin->id}/security/disable", ['reason' => 'Last super admin'])
            ->assertRedirect("/admin/users/{$admin->id}")
            ->assertSessionHasErrors('security');

        $admin = User::findOrFail($admin->id);
        $this->assertNull($admin->disabled_at);
        $this->assertSame(0, AdminAuditLog::where('action', 'user_disabled')->count());
    }

    public function test_admin_can_generate_password_setup_link_without_audit_token_leak(): void
    {
        $admin = $this->superAdmin('s33-link-admin@example.test');
        $target = $this->customer('s33-link-target@example.test');

        $response = $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/security/send-reset-link")
            ->assertRedirect("/admin/users/{$target->id}")
            ->assertSessionHas('password_setup_url');

        $setupUrl = (string) $response->baseResponse->getSession()->get('password_setup_url');
        $token = basename((string) parse_url($setupUrl, PHP_URL_PATH));

        $this->assertStringContainsString('/password/setup/', $setupUrl);
        $this->assertNotSame('', $token);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 's33-link-target@example.test']);

        $target = User::findOrFail($target->id);
        $this->assertNotNull($target->invited_at);

        $audit = AdminAuditLog::where('action', 'user_password_setup_link_created')->firstOrFail();
        $encoded = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $encoded);
    }

    public function test_password_setup_link_sets_password_deletes_token_and_clears_forced_reset(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-setup-complete@example.test');
        $user->forceFill(['force_password_reset_at' => now(), 'invited_at' => now()])->save();
        $this->storePasswordResetToken($user, 'valid-setup-token');

        $this->get('/password/setup/valid-setup-token?email=s33-setup-complete%40example.test')
            ->assertOk()
            ->assertSee('Set password');

        $this->post('/password/setup', [
            'email' => 's33-setup-complete@example.test',
            'token' => 'valid-setup-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect('/login')
            ->assertSessionHas('status', 'Password updated. You can now log in.');

        $user = User::findOrFail($user->id);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertNull($user->force_password_reset_at);
        $this->assertNotNull($user->last_password_reset_at);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 's33-setup-complete@example.test']);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_password_reset_completed')->count());
    }

    public function test_password_setup_rejects_invalid_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-invalid-token@example.test');
        $oldPassword = $user->password;
        $this->storePasswordResetToken($user, 'valid-token');

        $this->from('/password/setup/bad-token?email=s33-invalid-token%40example.test')
            ->post('/password/setup', [
                'email' => 's33-invalid-token@example.test',
                'token' => 'bad-token',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/password/setup/bad-token?email=s33-invalid-token%40example.test')
            ->assertSessionHasErrors('token');

        $user = User::findOrFail($user->id);
        $this->assertSame($oldPassword, $user->password);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 's33-invalid-token@example.test']);
    }

    public function test_password_setup_rejects_expired_token(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-expired-token@example.test');
        $oldPassword = $user->password;
        $this->storePasswordResetToken($user, 'expired-token', now()->subMinutes(61));

        $this->from('/password/setup/expired-token?email=s33-expired-token%40example.test')
            ->post('/password/setup', [
                'email' => 's33-expired-token@example.test',
                'token' => 'expired-token',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/password/setup/expired-token?email=s33-expired-token%40example.test')
            ->assertSessionHasErrors('token');

        $user = User::findOrFail($user->id);
        $this->assertSame($oldPassword, $user->password);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 's33-expired-token@example.test']);
        $this->assertSame(0, AdminAuditLog::where('action', 'user_password_reset_completed')->count());
    }

    public function test_forced_password_reset_gates_authenticated_routes_until_completed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-forced-reset@example.test');
        $user->forceFill([
            'password' => Hash::make('OldPassword123!'),
            'force_password_reset_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/password/forced-reset');

        $this->actingAs($user)
            ->get('/password/forced-reset')
            ->assertOk()
            ->assertSee('Change password');

        $this->actingAs($user)
            ->post('/password/forced-reset', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/dashboard');

        $user = User::findOrFail($user->id);
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertNull($user->force_password_reset_at);
        $this->assertNotNull($user->last_password_reset_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_forced_password_reset_completed')->count());

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_forced_password_reset_user_cannot_bypass_gate_with_setup_link(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s33-forced-setup-bypass@example.test');
        $oldPassword = $user->password;
        $user->forceFill(['force_password_reset_at' => now()])->save();
        $this->storePasswordResetToken($user, 'forced-setup-token');

        $this->actingAs($user)
            ->get('/password/setup/forced-setup-token?email=s33-forced-setup-bypass%40example.test')
            ->assertRedirect('/password/forced-reset');

        $this->actingAs($user)
            ->from('/password/setup/forced-setup-token?email=s33-forced-setup-bypass%40example.test')
            ->post('/password/setup', [
                'email' => 's33-forced-setup-bypass@example.test',
                'token' => 'forced-setup-token',
                'password' => 'BypassPassword123!',
                'password_confirmation' => 'BypassPassword123!',
            ])
            ->assertRedirect('/password/forced-reset');

        $user = User::findOrFail($user->id);
        $this->assertSame($oldPassword, $user->password);
        $this->assertNotNull($user->force_password_reset_at);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 's33-forced-setup-bypass@example.test']);
        $this->assertSame(0, AdminAuditLog::where('action', 'user_password_reset_completed')->count());
    }

    public function test_admin_security_mutations_roll_back_when_audit_fails(): void
    {
        $admin = $this->superAdmin('s33-audit-failure-admin@example.test');
        $target = $this->customer('s33-audit-failure-target@example.test');
        $this->bindFailingAuditLogger();
        $this->withoutExceptionHandling();

        $this->assertAuditFailure(function () use ($admin, $target): void {
            $this->actingAs($admin)->post("/admin/users/{$target->id}/security/force-password-reset");
        });
        $this->assertNull(User::findOrFail($target->id)->force_password_reset_at);

        $target->forceFill(['force_password_reset_at' => now()])->save();
        $this->assertAuditFailure(function () use ($admin, $target): void {
            $this->actingAs($admin)->post("/admin/users/{$target->id}/security/clear-force-password-reset");
        });
        $this->assertNotNull(User::findOrFail($target->id)->force_password_reset_at);

        $target->forceFill(['force_password_reset_at' => null])->save();
        $this->assertAuditFailure(function () use ($admin, $target): void {
            $this->actingAs($admin)->post("/admin/users/{$target->id}/security/disable", ['reason' => 'Audit failure']);
        });
        $this->assertNull(User::findOrFail($target->id)->disabled_at);

        $target->forceFill(['disabled_at' => now(), 'disabled_reason' => 'Pre-existing disable'])->save();
        $this->assertAuditFailure(function () use ($admin, $target): void {
            $this->actingAs($admin)->post("/admin/users/{$target->id}/security/enable");
        });
        $target = User::findOrFail($target->id);
        $this->assertNotNull($target->disabled_at);
        $this->assertSame('Pre-existing disable', $target->disabled_reason);

        $this->assertSame(0, AdminAuditLog::whereIn('action', [
            'user_force_password_reset_required',
            'user_force_password_reset_cleared',
            'user_disabled',
            'user_enabled',
        ])->count());
    }

    private function superAdmin(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return $this->userWithRole('super_admin', $email);
    }

    private function customer(string $email): User
    {
        return $this->userWithRole('customer', $email);
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

    private function storePasswordResetToken(User $user, string $token, ?DateTimeInterface $createdAt = null): void
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => $createdAt ?? now()],
        );
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

    private function assertAuditFailure(callable $request): void
    {
        try {
            $request();
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit failed', $exception->getMessage());
        }
    }
}
