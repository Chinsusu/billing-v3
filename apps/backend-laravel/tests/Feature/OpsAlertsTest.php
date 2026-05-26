<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\OpsAlertEvent;
use App\Models\OpsAlertRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\ScheduledTaskRun;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpsAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_view_ops_alert_rule_without_exposing_secret(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/ops-alert-rules', [
            'name' => 'Ops Health Webhook',
            'type' => 'ops_health',
            'enabled' => '1',
            'severity' => 'warning',
            'cooldown_minutes' => 15,
            'webhook_url' => 'https://alerts.example.test/hooks/billing',
            'webhook_secret' => 'super-secret-alert-key',
        ])->assertRedirect('/admin/ops-alert-rules');

        $rule = OpsAlertRule::firstOrFail();
        $this->assertSame('https://alerts.example.test/hooks/billing', $rule->webhook_url);
        $this->assertSame('super-secret-alert-key', $rule->webhook_secret);

        $this->actingAs($admin)->put("/admin/ops-alert-rules/{$rule->id}", [
            'name' => 'Ops Health Webhook Updated',
            'type' => 'ops_health',
            'enabled' => '1',
            'severity' => 'critical',
            'cooldown_minutes' => 30,
            'webhook_url' => '',
            'webhook_secret' => '',
        ])->assertRedirect('/admin/ops-alert-rules');

        $rule->refresh();
        $this->assertSame('Ops Health Webhook Updated', $rule->name);
        $this->assertSame('critical', $rule->severity);
        $this->assertSame(30, $rule->cooldown_minutes);
        $this->assertSame('https://alerts.example.test/hooks/billing', $rule->webhook_url);
        $this->assertSame('super-secret-alert-key', $rule->webhook_secret);

        $this->actingAs($admin)
            ->get('/admin/ops-alert-rules')
            ->assertOk()
            ->assertSee('Ops Alert Rules')
            ->assertSee('Ops Health Webhook Updated')
            ->assertDontSee('https://alerts.example.test/hooks/billing')
            ->assertDontSee('super-secret-alert-key');
    }

    public function test_ops_alerts_evaluate_creates_task_and_queue_alert_events(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $rule = $this->opsHealthRule();
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer);
        $this->seedHealthyOpsState(exceptTasks: ['bank_sync_payments']);
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
        ]);
        ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 3,
            'idempotency_key' => 'ops-alert-provisioning-failed',
            'payload' => ['product' => ['code' => $service->product_code]],
            'last_error' => 'Provisioning failed.',
        ]);

        $this->artisan('ops-alerts:evaluate')->assertExitCode(0);

        $this->assertDatabaseHas('ops_alert_events', [
            'ops_alert_rule_id' => $rule->id,
            'fingerprint' => 'task:bank_sync_payments:warning',
            'status' => 'open',
            'title' => 'Scheduled task bank_sync_payments is warning',
        ]);
        $this->assertDatabaseHas('ops_alert_events', [
            'ops_alert_rule_id' => $rule->id,
            'fingerprint' => 'queue:provisioning:failed',
            'status' => 'open',
            'title' => 'Provisioning queue is failed',
        ]);
    }

    public function test_ops_alerts_evaluate_respects_cooldown_and_updates_last_seen(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $this->opsHealthRule(['cooldown_minutes' => 15]);
        $this->seedHealthyOpsState(exceptTasks: ['bank_sync_payments']);
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
        ]);

        $this->artisan('ops-alerts:evaluate')->assertExitCode(0);
        $event = OpsAlertEvent::where('fingerprint', 'task:bank_sync_payments:warning')->firstOrFail();
        $this->assertSame('2026-05-24 09:00:00', $event->last_seen_at->toDateTimeString());

        $this->travelTo(Carbon::parse('2026-05-24 09:05:00'));
        $this->artisan('ops-alerts:evaluate')->assertExitCode(0);

        $this->assertSame(1, OpsAlertEvent::where('fingerprint', 'task:bank_sync_payments:warning')->count());
        $this->assertSame('2026-05-24 09:05:00', $event->refresh()->last_seen_at->toDateTimeString());
    }

    public function test_ops_alerts_evaluate_posts_signed_webhook_payload(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        Http::fake(['https://alerts.example.test/hooks/billing' => Http::response(['ok' => true], 204)]);
        $this->opsHealthRule([
            'webhook_url' => 'https://alerts.example.test/hooks/billing',
            'webhook_secret' => 'alert-signing-secret',
        ]);
        $this->seedHealthyOpsState(exceptTasks: ['bank_sync_payments']);
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
        ]);

        $this->artisan('ops-alerts:evaluate')->assertExitCode(0);

        Http::assertSent(function (Request $request): bool {
            $expectedSignature = hash_hmac('sha256', $request->body(), 'alert-signing-secret');

            return $request->url() === 'https://alerts.example.test/hooks/billing'
                && $request['fingerprint'] === 'task:bank_sync_payments:warning'
                && $request['title'] === 'Scheduled task bank_sync_payments is warning'
                && $request->hasHeader('X-Billing-Signature', $expectedSignature);
        });
        $this->assertSame('delivered', OpsAlertEvent::firstOrFail()->delivery_status);
    }

    public function test_ops_alerts_evaluate_records_webhook_failure_without_crashing(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        Http::fake(['https://alerts.example.test/hooks/billing' => Http::response('failed', 500)]);
        $this->opsHealthRule(['webhook_url' => 'https://alerts.example.test/hooks/billing']);
        $this->seedHealthyOpsState(exceptTasks: ['bank_sync_payments']);
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
        ]);

        $this->artisan('ops-alerts:evaluate')->assertExitCode(0);

        $event = OpsAlertEvent::firstOrFail();
        $this->assertSame('failed', $event->delivery_status);
        $this->assertStringContainsString('HTTP 500', $event->delivery_error);
    }

    public function test_admin_can_acknowledge_and_resolve_alert_event(): void
    {
        $admin = $this->adminUser();
        $event = OpsAlertEvent::create([
            'ops_alert_rule_id' => $this->opsHealthRule()->id,
            'fingerprint' => 'task:bank_sync_payments:warning',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Scheduled task bank_sync_payments is warning',
            'message' => 'No successful run in the last 3 minutes.',
            'context' => ['task' => 'bank_sync_payments'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'delivery_status' => 'skipped',
        ]);

        $this->actingAs($admin)
            ->post("/admin/ops-alert-events/{$event->id}/acknowledge")
            ->assertRedirect('/admin/ops-alert-events');
        $event->refresh();
        $this->assertSame('acknowledged', $event->status);
        $this->assertSame($admin->id, $event->acknowledged_by_id);
        $this->assertNotNull($event->acknowledged_at);

        $this->actingAs($admin)
            ->post("/admin/ops-alert-events/{$event->id}/resolve")
            ->assertRedirect('/admin/ops-alert-events');
        $event->refresh();
        $this->assertSame('resolved', $event->status);
        $this->assertSame($admin->id, $event->resolved_by_id);
        $this->assertNotNull($event->resolved_at);
    }

    public function test_customer_cannot_access_ops_alert_pages(): void
    {
        $customer = $this->customerUser();
        $event = OpsAlertEvent::create([
            'ops_alert_rule_id' => $this->opsHealthRule()->id,
            'fingerprint' => 'task:bank_sync_payments:warning',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Scheduled task bank_sync_payments is warning',
            'message' => 'No successful run in the last 3 minutes.',
            'context' => ['task' => 'bank_sync_payments'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'delivery_status' => 'skipped',
        ]);

        $this->actingAs($customer)->get('/admin/ops-alert-rules')->assertForbidden();
        $this->actingAs($customer)->get('/admin/ops-alert-events')->assertForbidden();
        $this->actingAs($customer)->post("/admin/ops-alert-events/{$event->id}/acknowledge")->assertForbidden();
    }

    public function test_scheduler_registers_ops_alert_evaluation_task(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run ops_alerts_evaluate')
            ->assertExitCode(0);
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

    /**
     * @param  list<string>  $exceptTasks
     */
    private function seedHealthyOpsState(array $exceptTasks = []): void
    {
        BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'enabled' => true,
        ]);

        foreach (['bank_sync_payments', 'provider_actions_work', 'services_expire', 'service_cancellations_process_scheduled', 'provider_actions_recover_stuck'] as $task) {
            if (in_array($task, $exceptTasks, true)) {
                continue;
            }

            ScheduledTaskRun::create([
                'task' => $task,
                'command' => $task,
                'status' => 'success',
                'started_at' => now()->subMinute(),
                'finished_at' => now()->subMinute(),
                'duration_ms' => 20,
                'exit_code' => 0,
            ]);
        }
    }

    private function serviceFor(User $user): Service
    {
        $product = Product::factory()->create([
            'code' => 'ops-alert-product',
            'name' => 'Ops Alert Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);
        $order = Order::factory()->for($user)->create(['currency' => 'VND']);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
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
