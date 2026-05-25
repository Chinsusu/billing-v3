<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_reports_database_status(): void
    {
        $this->getJson('/ops/readiness')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok');
    }

    public function test_audit_log_retention_command_purges_old_logs_only(): void
    {
        $user = User::factory()->create(['email' => 's40-admin@example.test']);
        AdminAuditLog::create([
            'actor_id' => $user->id,
            'actor_email' => $user->email,
            'action' => 'old_action',
            'auditable_type' => User::class,
            'auditable_id' => (string) $user->id,
            'auditable_label' => $user->email,
            'route_name' => null,
            'ip_address' => null,
            'user_agent' => null,
            'before' => [],
            'after' => [],
            'metadata' => [],
            'created_at' => now()->subDays(120),
            'updated_at' => now()->subDays(120),
        ]);
        AdminAuditLog::create([
            'actor_id' => $user->id,
            'actor_email' => $user->email,
            'action' => 'recent_action',
            'auditable_type' => User::class,
            'auditable_id' => (string) $user->id,
            'auditable_label' => $user->email,
            'route_name' => null,
            'ip_address' => null,
            'user_agent' => null,
            'before' => [],
            'after' => [],
            'metadata' => [],
        ]);

        $this->artisan('ops:purge-audit-logs', ['--days' => 90])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'old_action']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'recent_action']);
    }
}
