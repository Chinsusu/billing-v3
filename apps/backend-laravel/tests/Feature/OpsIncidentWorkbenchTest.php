<?php

namespace Tests\Feature;

use App\Models\OpsAlertEvent;
use App\Models\OpsAlertRule;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OpsIncidentWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ops_alert_event_detail_with_context_delivery_and_actions(): void
    {
        $admin = $this->adminUser();
        $rule = $this->opsHealthRule();
        $event = $this->opsAlertEvent($rule, [
            'status' => 'open',
            'delivery_status' => 'failed',
            'delivery_error' => 'HTTP 500 from incident endpoint.',
            'context' => [
                'source' => 'scheduled_task',
                'task' => 'bank_sync_payments',
                'last_status' => 'failed',
            ],
        ]);

        $response = $this->actingAs($admin)->get("/admin/ops-alert-events/{$event->id}");

        $response->assertOk()
            ->assertSee('Ops Alert Event')
            ->assertSee('Scheduled task bank_sync_payments is failed')
            ->assertSee('Ops Health')
            ->assertSee('task:bank_sync_payments:failed')
            ->assertSee('HTTP 500 from incident endpoint.')
            ->assertSee('bank_sync_payments')
            ->assertSee("action=\"/admin/ops-alert-events/{$event->id}/acknowledge\"", false)
            ->assertSee("action=\"/admin/ops-alert-events/{$event->id}/resolve\"", false);
    }

    public function test_ops_alert_index_links_event_rows_to_detail(): void
    {
        $admin = $this->adminUser();
        $event = $this->opsAlertEvent($this->opsHealthRule());

        $this->actingAs($admin)
            ->get('/admin/ops-alert-events')
            ->assertOk()
            ->assertSee("href=\"/admin/ops-alert-events/{$event->id}\"", false);
    }

    public function test_admin_can_list_scheduled_task_runs_with_task_and_status_filters(): void
    {
        $admin = $this->adminUser();
        $visibleRun = $this->scheduledTaskRun([
            'task' => 'provider_actions_work',
            'command' => 'provider-actions:work --limit=50',
            'status' => 'failed',
            'exit_code' => 1,
            'output' => 'Provider action output.',
            'error' => 'Provider action failed.',
        ]);
        $this->scheduledTaskRun([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'exit_code' => 0,
            'output' => 'Bank sync ok.',
            'error' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin/scheduled-task-runs?task=provider_actions_work&status=failed')
            ->assertOk()
            ->assertSee('Scheduled Task Runs')
            ->assertSee('provider_actions_work')
            ->assertSee('provider-actions:work --limit=50')
            ->assertSee('failed')
            ->assertSee('Provider action failed.')
            ->assertSee("href=\"/admin/scheduled-task-runs/{$visibleRun->id}\"", false)
            ->assertDontSee('Bank sync ok.');
    }

    public function test_admin_can_view_scheduled_task_run_detail_with_output_and_error(): void
    {
        $admin = $this->adminUser();
        $run = $this->scheduledTaskRun([
            'task' => 'ops_alerts_evaluate',
            'command' => 'ops-alerts:evaluate',
            'status' => 'failed',
            'exit_code' => 1,
            'output' => 'Created alert candidates before failing.',
            'error' => 'Webhook delivery exception.',
        ]);

        $this->actingAs($admin)
            ->get("/admin/scheduled-task-runs/{$run->id}")
            ->assertOk()
            ->assertSee('Scheduled Task Run')
            ->assertSee('ops_alerts_evaluate')
            ->assertSee('ops-alerts:evaluate')
            ->assertSee('Created alert candidates before failing.')
            ->assertSee('Webhook delivery exception.');
    }

    public function test_ops_health_links_scheduled_task_rows_to_run_history_and_latest_detail(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        $run = $this->scheduledTaskRun([
            'task' => 'provider_actions_work',
            'command' => 'provider-actions:work --limit=50',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
            'duration_ms' => 25,
            'exit_code' => 0,
            'output' => 'Provider action jobs processed=0 failed=0.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/ops-health')
            ->assertOk()
            ->assertSee('provider_actions_work')
            ->assertSee('href="/admin/scheduled-task-runs?task=provider_actions_work"', false)
            ->assertSee("href=\"/admin/scheduled-task-runs/{$run->id}\"", false);
    }

    public function test_customer_cannot_access_ops_incident_workbench_pages(): void
    {
        $customer = $this->customerUser();
        $event = $this->opsAlertEvent($this->opsHealthRule());
        $run = $this->scheduledTaskRun();

        $this->actingAs($customer)->get("/admin/ops-alert-events/{$event->id}")->assertForbidden();
        $this->actingAs($customer)->get('/admin/scheduled-task-runs')->assertForbidden();
        $this->actingAs($customer)->get("/admin/scheduled-task-runs/{$run->id}")->assertForbidden();
    }

    private function opsHealthRule(array $overrides = []): OpsAlertRule
    {
        return OpsAlertRule::create($overrides + [
            'name' => 'Ops Health',
            'type' => 'ops_health',
            'enabled' => true,
            'severity' => 'warning',
            'cooldown_minutes' => 15,
        ]);
    }

    private function opsAlertEvent(OpsAlertRule $rule, array $overrides = []): OpsAlertEvent
    {
        return OpsAlertEvent::create($overrides + [
            'ops_alert_rule_id' => $rule->id,
            'fingerprint' => 'task:bank_sync_payments:failed',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Scheduled task bank_sync_payments is failed',
            'message' => 'Latest run failed with exit code 1.',
            'context' => ['task' => 'bank_sync_payments'],
            'first_seen_at' => now()->subMinutes(5),
            'last_seen_at' => now(),
            'delivery_status' => 'skipped',
        ]);
    }

    private function scheduledTaskRun(array $overrides = []): ScheduledTaskRun
    {
        return ScheduledTaskRun::create($overrides + [
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
            'duration_ms' => 30,
            'exit_code' => 0,
            'output' => 'No bank integrations.',
            'error' => null,
        ]);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
