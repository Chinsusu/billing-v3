<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningExecutionLog;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRunbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_service_runbook_context(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('runbook-customer@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'external_id' => 'provider-service-runbook',
            'provisioned_at' => now(),
            'expires_at' => now()->addDays(10),
        ]);
        $provisioningJob = $this->provisioningJob($service, ['status' => 'processed']);
        $providerActionJob = $this->providerActionJob($service, [
            'action' => 'sync',
            'status' => 'failed',
            'last_error' => 'Provider sync failed.',
        ]);
        ProvisioningExecutionLog::create([
            'provisioning_job_id' => $provisioningJob->id,
            'service_id' => $service->id,
            'provider_account_id' => null,
            'action' => 'provider_service_sync',
            'driver' => 'generic_http',
            'endpoint' => 'https://provider.example.test/services/provider-service-runbook',
            'status' => 'failed',
            'http_status' => 500,
            'duration_ms' => 87,
            'error_code' => 'provider_http_error',
            'error_message' => 'Provider sync failed.',
            'request_payload' => ['api_key' => '***redacted***'],
            'response_payload' => ['message' => 'down'],
        ]);

        $this->actingAs($admin)
            ->get("/admin/services/{$service->id}")
            ->assertOk()
            ->assertSee('Service Runbook')
            ->assertSee('runbook-customer@example.test')
            ->assertSee($service->product_name)
            ->assertSee($service->external_id)
            ->assertSee($service->order->order_number)
            ->assertSee($provisioningJob->idempotency_key)
            ->assertSee($providerActionJob->idempotency_key)
            ->assertSee('Provider sync failed.')
            ->assertSee('provider_http_error')
            ->assertSee('***redacted***')
            ->assertDontSee('provider-secret-1234');
    }

    public function test_admin_services_index_links_to_service_runbook(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('service-list@example.test');
        $service = $this->serviceFor($customer, ['product_name' => 'Runbook Proxy']);

        $this->actingAs($admin)
            ->get('/admin/services')
            ->assertOk()
            ->assertSee('Runbook Proxy')
            ->assertSee('href="/admin/services/'.$service->id.'"', false);
    }

    public function test_customer_service_detail_shows_action_history_without_raw_payloads(): void
    {
        $customer = $this->customerUser('customer-runbook@example.test');
        $service = $this->serviceFor($customer, ['status' => 'active']);
        $this->provisioningJob($service, ['status' => 'processed']);
        $this->providerActionJob($service, [
            'action' => 'cancel',
            'status' => 'pending',
            'idempotency_key' => 'service-cancel-visible',
        ]);
        ProvisioningExecutionLog::create([
            'provisioning_job_id' => null,
            'service_id' => $service->id,
            'provider_account_id' => null,
            'action' => 'provider_service_cancel',
            'driver' => 'generic_http',
            'endpoint' => 'https://provider.example.test/cancel',
            'status' => 'failed',
            'http_status' => 409,
            'duration_ms' => 30,
            'error_code' => 'provider_conflict',
            'error_message' => 'Provider rejected cancel.',
            'request_payload' => ['secret' => 'provider-secret-1234'],
            'response_payload' => ['message' => 'conflict'],
        ]);

        $this->actingAs($customer)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('Provider Action History')
            ->assertSee('cancel')
            ->assertSee('pending')
            ->assertSee('Provider Execution Summary')
            ->assertSee('provider_service_cancel')
            ->assertSee('provider_conflict')
            ->assertDontSee('provider-secret-1234')
            ->assertDontSee('https://provider.example.test/cancel');
    }

    public function test_customer_cannot_access_admin_service_runbook(): void
    {
        $customer = $this->customerUser('denied-runbook@example.test');
        $service = $this->serviceFor($customer);

        $this->actingAs($customer)
            ->get("/admin/services/{$service->id}")
            ->assertForbidden();
    }

    private function serviceFor(User $user, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'runbook-proxy-30d',
            'name' => 'Runbook Proxy',
            'type' => 'proxy',
            'status' => 'active',
        ]);
        $order = Order::factory()->for($user)->create(['order_number' => 'ORD-RUNBOOK-001']);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create($overrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);
    }

    private function provisioningJob(Service $service, array $overrides = []): ProvisioningJob
    {
        return ProvisioningJob::create($overrides + [
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'type' => 'provision_service',
            'status' => 'pending',
            'attempts' => 1,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
        ]);
    }

    private function providerActionJob(Service $service, array $overrides = []): ProviderActionJob
    {
        return ProviderActionJob::create($overrides + [
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'provider_account_id' => null,
            'action' => 'sync',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => "service-sync:{$service->id}:manual",
            'payload' => ['context' => []],
        ]);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
