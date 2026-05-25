<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Security\TotpService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MfaSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_require_and_reset_mfa_for_user(): void
    {
        $admin = $this->superAdmin('s34-admin@example.test');
        $target = $this->customer('s34-target@example.test');

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/mfa/require")
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNotNull($target->mfa_required_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_mfa_required')->count());

        $this->actingAs($target)
            ->get('/dashboard')
            ->assertRedirect('/mfa/setup');

        $target->forceFill([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => [Hash::make('RECOVERY-CODE')],
        ])->save();

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/mfa/reset")
            ->assertRedirect("/admin/users/{$target->id}");

        $target = User::findOrFail($target->id);
        $this->assertNull($target->mfa_secret);
        $this->assertNull($target->mfa_enabled_at);
        $this->assertNull($target->mfa_required_at);
        $this->assertSame([], $target->mfa_recovery_codes ?? []);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_mfa_reset')->count());
    }

    public function test_user_can_enable_mfa_and_must_complete_challenge_after_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s34-mfa-user@example.test');
        $user->forceFill([
            'password' => Hash::make('Password123!'),
            'mfa_required_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get('/mfa/setup')
            ->assertOk()
            ->assertSee('Authenticator Secret');

        $user = User::findOrFail($user->id);
        $code = app(TotpService::class)->code($user->mfa_secret);

        $this->actingAs($user)
            ->post('/mfa/enable', ['code' => $code])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('mfa_recovery_codes');

        $user = User::findOrFail($user->id);
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertNull($user->mfa_required_at);
        $this->assertCount(8, $user->mfa_recovery_codes);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 's34-mfa-user@example.test',
            'password' => 'Password123!',
        ])->assertRedirect('/mfa/challenge');

        $this->get('/dashboard')->assertRedirect('/mfa/challenge');

        $code = app(TotpService::class)->code(User::findOrFail($user->id)->mfa_secret);
        $this->post('/mfa/challenge', ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk();
        $this->assertSame(1, AdminAuditLog::where('action', 'user_mfa_enabled')->count());
    }

    public function test_recovery_code_completes_mfa_once(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = $this->customer('s34-recovery@example.test');
        $user->forceFill([
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_enabled_at' => now(),
            'mfa_recovery_codes' => [Hash::make('ONE-TIME-CODE')],
        ])->save();

        $this->actingAs($user)
            ->withSession(['mfa_passed' => false])
            ->post('/mfa/recovery', ['recovery_code' => 'ONE-TIME-CODE'])
            ->assertRedirect('/dashboard');

        $this->assertSame([], User::findOrFail($user->id)->mfa_recovery_codes ?? []);

        $this->actingAs($user)
            ->withSession(['mfa_passed' => false])
            ->from('/mfa/challenge')
            ->post('/mfa/recovery', ['recovery_code' => 'ONE-TIME-CODE'])
            ->assertRedirect('/mfa/challenge')
            ->assertSessionHasErrors('recovery_code');
    }

    public function test_admin_can_revoke_user_database_session(): void
    {
        $admin = $this->superAdmin('s34-session-admin@example.test');
        $target = $this->customer('s34-session-target@example.test');
        DB::table('sessions')->insert([
            'id' => 'session-to-revoke',
            'user_id' => $target->id,
            'ip_address' => '203.0.113.55',
            'user_agent' => 'S34 Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/sessions/session-to-revoke/revoke")
            ->assertRedirect("/admin/users/{$target->id}");

        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);
        $this->assertSame(1, AdminAuditLog::where('action', 'user_session_revoked')->count());
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
}
