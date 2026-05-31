<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\BankIntegration;
use App\Models\NotificationTemplate;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_and_provider_config_changes_are_audited_without_secret_leaks(): void
    {
        $admin = $this->adminUser('audit-admin@example.test');

        $this->actingAs($admin)
            ->post('/admin/bank-integrations', [
                'provider' => 'private_bank',
                'name' => 'Private Bank',
                'base_url' => 'https://bank.example.test',
                'transactions_path' => '/api/transactions',
                'account_number' => '123456789',
                'enabled' => '1',
                'api_key' => 'private-api-secret-1234',
                'webhook_secret' => 'webhook-secret-5678',
            ])
            ->assertRedirect('/admin/bank-integrations');

        $bank = BankIntegration::firstOrFail();
        $created = $this->latestAuditLog(BankIntegration::class, $bank->id, 'created');
        $createdAfter = $this->jsonColumn($created, 'after');

        $this->assertSame($admin->id, $created->actor_id);
        $this->assertSame('audit-admin@example.test', $created->actor_email);
        $this->assertSame('admin.bank-integrations.store', $created->route_name);
        $this->assertSame('Private Bank', $created->auditable_label);
        $this->assertSame('Private Bank', $createdAfter['name']);
        $this->assertSame('[redacted]', $createdAfter['api_key']);
        $this->assertSame('[redacted]', $createdAfter['webhook_secret']);
        $this->assertLogDoesNotContainSecrets($created, ['private-api-secret-1234', 'webhook-secret-5678']);

        $this->actingAs($admin)
            ->put("/admin/bank-integrations/{$bank->id}", [
                'provider' => 'private_bank',
                'name' => 'Updated Bank',
                'base_url' => 'https://new-bank.example.test',
                'transactions_path' => '/api/transactions',
                'account_number' => 'NEW-ACCOUNT',
                'enabled' => '0',
                'api_key' => 'private-api-secret-9999',
                'webhook_secret' => '',
            ])
            ->assertRedirect('/admin/bank-integrations');

        $updated = $this->latestAuditLog(BankIntegration::class, $bank->id, 'updated');
        $updatedBefore = $this->jsonColumn($updated, 'before');
        $updatedAfter = $this->jsonColumn($updated, 'after');

        $this->assertSame('Private Bank', $updatedBefore['name']);
        $this->assertSame('Updated Bank', $updatedAfter['name']);
        $this->assertSame(true, $updatedBefore['enabled']);
        $this->assertSame(false, $updatedAfter['enabled']);
        $this->assertSame('[redacted]', $updatedAfter['api_key']);
        $this->assertArrayNotHasKey('webhook_secret', $updatedAfter);
        $this->assertLogDoesNotContainSecrets($updated, ['private-api-secret-1234', 'private-api-secret-9999', 'webhook-secret-5678']);

        $this->actingAs($admin)
            ->post('/admin/provisioning-provider-accounts', [
                'slug' => 'provider-a-main',
                'name' => 'Provider A Main',
                'driver' => 'generic_http',
                'base_url' => 'https://provider-a.example.test',
                'provision_path' => '/api/provision',
                'auth_type' => 'bearer',
                'auth_header_name' => '',
                'api_key' => 'provider-secret-1234',
                'callback_secret' => 'callback-secret-5678',
                'enabled' => '1',
                'timeout_seconds' => 20,
                'request_template' => '{"source":"billing"}',
                'response_external_id_path' => 'data.id',
                'response_status_path' => 'data.status',
                'response_config_path' => 'data.config',
            ])
            ->assertRedirect('/admin/provisioning-provider-accounts');

        $provider = ProvisioningProviderAccount::where('slug', 'provider-a-main')->firstOrFail();
        $providerLog = $this->latestAuditLog(ProvisioningProviderAccount::class, $provider->id, 'created');
        $providerAfter = $this->jsonColumn($providerLog, 'after');

        $this->assertSame('Provider A Main', $providerLog->auditable_label);
        $this->assertSame('generic_http', $providerAfter['driver']);
        $this->assertSame('[redacted]', $providerAfter['api_key']);
        $this->assertSame('[redacted]', $providerAfter['callback_secret']);
        $this->assertLogDoesNotContainSecrets($providerLog, ['provider-secret-1234', 'callback-secret-5678']);

        $this->actingAs($admin)
            ->get('/admin/audit-logs?actor=audit-admin@example.test&action=updated&auditable_type=BankIntegration')
            ->assertOk()
            ->assertSee('Updated Bank')
            ->assertSee('updated')
            ->assertDontSee('private-api-secret-9999');

        $this->actingAs($admin)
            ->get("/admin/audit-logs/{$updated->id}")
            ->assertOk()
            ->assertSee('Updated Bank')
            ->assertSee('admin.bank-integrations.update')
            ->assertDontSee('private-api-secret-9999');
    }

    public function test_product_template_and_manual_operations_are_audited(): void
    {
        $this->seed(NotificationTemplateSeeder::class);
        $admin = $this->adminUser('ops-audit@example.test');
        $customer = $this->customerUser('audit-customer@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 100000]);

        $this->actingAs($admin)
            ->post('/admin/products', [
                'code' => 'audit-proxy-30d',
                'name' => 'Audit Proxy 30 Days',
                'type' => 'proxy',
                'status' => 'active',
                'price_amount' => 99000,
                'currency' => 'VND',
                'duration_days' => 30,
                'description' => 'Audit test plan',
            ])
            ->assertRedirect('/admin/products');

        $product = Product::where('code', 'audit-proxy-30d')->firstOrFail();
        $this->assertSame('Audit Proxy 30 Days', $this->latestAuditLog(Product::class, $product->id, 'created')->auditable_label);

        $this->actingAs($admin)
            ->put("/admin/products/{$product->id}", [
                'code' => 'audit-proxy-30d',
                'name' => 'Audit Proxy Updated',
                'type' => 'proxy',
                'status' => 'draft',
                'price_amount' => 109000,
                'currency' => 'VND',
                'duration_days' => 30,
                'description' => 'Updated audit test plan',
            ])
            ->assertRedirect('/admin/products');

        $productUpdate = $this->latestAuditLog(Product::class, $product->id, 'updated');
        $this->assertSame(99000, $this->jsonColumn($productUpdate, 'before')['price_amount']);
        $this->assertSame(109000, $this->jsonColumn($productUpdate, 'after')['price_amount']);

        $this->actingAs($admin)->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/products');
        $this->assertSame('archived', $this->jsonColumn($this->latestAuditLog(Product::class, $product->id, 'archived'), 'after')['status']);

        $template = NotificationTemplate::where('type', 'invoice_paid')->firstOrFail();
        $this->actingAs($admin)
            ->put("/admin/notification-templates/{$template->id}", [
                'name' => 'Invoice paid audit',
                'enabled' => '1',
                'subject_template' => 'Invoice {{invoice_number}} paid',
                'body_template' => 'Invoice {{invoice_number}} paid.',
            ])
            ->assertRedirect('/admin/notification-templates');
        $this->assertSame('Invoice paid audit', $this->latestAuditLog(NotificationTemplate::class, $template->id, 'updated')->auditable_label);

        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'credit',
            'amount' => 50000,
            'currency' => 'VND',
            'reason' => 'Manual audit credit',
            'reference' => 'AUDIT-001',
        ])->assertRedirect("/admin/customers/{$customer->id}");

        $walletAudit = $this->latestAuditLog(User::class, $customer->id, 'wallet_adjusted');
        $this->assertSame('credit', $this->jsonColumn($walletAudit, 'metadata')['direction']);
        $this->assertSame(50000, $this->jsonColumn($walletAudit, 'metadata')['amount']);

        $service = Service::factory()->for($customer)->create(['product_name' => 'Audit Service']);
        $job = ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => null,
            'action' => 'sync',
            'status' => 'failed',
            'attempts' => 3,
            'max_attempts' => 3,
            'idempotency_key' => 'audit-provider-action-retry',
            'payload' => ['context' => []],
            'available_at' => null,
            'processed_at' => now(),
            'last_error' => 'Failed before audit retry.',
        ]);

        $this->actingAs($admin)
            ->post("/admin/provider-action-jobs/{$job->id}/retry")
            ->assertRedirect('/admin/provider-action-jobs');

        $retryAudit = $this->latestAuditLog(ProviderActionJob::class, $job->id, 'provider_action_retried');
        $this->assertSame('failed', $this->jsonColumn($retryAudit, 'before')['status']);
        $this->assertSame('pending', $this->jsonColumn($retryAudit, 'after')['status']);
    }

    public function test_audit_log_routes_are_protected(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser();

        $this->get('/admin/audit-logs')->assertRedirect('/login');

        $this->actingAs($customer)
            ->get('/admin/audit-logs')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('/admin/audit-logs', false);
    }

    public function test_audit_log_index_uses_operations_layout_and_safe_pagination_controls(): void
    {
        $admin = $this->adminUser('audit-layout@example.test');

        foreach (range(1, 21) as $index) {
            AdminAuditLog::create([
                'actor_id' => $admin->id,
                'actor_email' => $admin->email,
                'action' => 'updated',
                'auditable_type' => Product::class,
                'auditable_id' => (string) $index,
                'auditable_label' => sprintf('Audit product %02d', $index),
                'route_name' => 'admin.products.update',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Feature Test',
                'before' => [],
                'after' => ['name' => sprintf('Audit product %02d', $index)],
                'metadata' => [],
                'created_at' => now()->subMinutes(21 - $index),
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin/audit-logs?actor=audit-layout@example.test&action=updated')
            ->assertOk()
            ->assertSee('data-audit-log-index', false)
            ->assertSee('invoice-filter-panel audit-log-filter-panel', false)
            ->assertSee('audit-log-filter-form', false)
            ->assertSee('audit-log-filter-fields', false)
            ->assertSee('audit-log-filter-actions', false)
            ->assertSee('list="audit-log-actor-options"', false)
            ->assertSee('id="audit-log-actor-options"', false)
            ->assertSee('<option value="audit-layout@example.test">', false)
            ->assertSee('list="audit-log-action-options"', false)
            ->assertSee('id="audit-log-action-options"', false)
            ->assertSee('<option value="updated">', false)
            ->assertSee('list="audit-log-subject-type-options"', false)
            ->assertSee('id="audit-log-subject-type-options"', false)
            ->assertSee('<option value="Product">', false)
            ->assertSee('list="audit-log-subject-id-options"', false)
            ->assertSee('id="audit-log-subject-id-options"', false)
            ->assertSee('<option value="21" label="Audit product 21">', false)
            ->assertDontSee('Review operator changes, security events, and system mutations.')
            ->assertSee('data-admin-pagination="audit-logs"', false)
            ->assertSee('aria-label="Audit Logs pagination"', false)
            ->assertSee('viewBox="0 0 24 24"', false)
            ->assertDontSee('Pagination Navigation')
            ->assertSee('Audit product 20')
            ->assertSee('/admin/audit-logs?actor=audit-layout%40example.test&amp;action=updated&amp;page=2', false);
    }

    private function adminUser(string $email = 'admin-audit@example.test'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => $email]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(string $email = 'customer-audit@example.test'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create(['email' => $email]);
        $customer->assignRole('customer');

        return $customer;
    }

    private function latestAuditLog(string $auditableType, string $auditableId, string $action): object
    {
        $log = DB::table('admin_audit_logs')
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->where('action', $action)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log, "Missing {$action} audit log for {$auditableType}:{$auditableId}.");

        return $log;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonColumn(object $row, string $column): array
    {
        $value = $row->{$column};

        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function assertLogDoesNotContainSecrets(object $log, array $secrets): void
    {
        $encoded = json_encode($log, JSON_THROW_ON_ERROR);

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }
}
