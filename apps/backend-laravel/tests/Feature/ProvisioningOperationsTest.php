<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProvisioningOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_service_detail_with_provisioning_history(): void
    {
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'external_id' => 'sandbox-proxy-service-1',
            'provisioned_at' => now(),
        ]);
        ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'processed',
            'attempts' => 1,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
            'processed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get("/services/{$service->id}")
            ->assertOk()
            ->assertSee('sandbox-proxy-service-1')
            ->assertSee('processed')
            ->assertSee('provision_service');
    }

    public function test_customer_cannot_view_another_users_service_detail(): void
    {
        $owner = $this->customerUser();
        $service = $this->serviceFor($owner);
        $other = User::factory()->create();
        $other->assignRole('customer');

        $this->actingAs($other)->get("/services/{$service->id}")->assertNotFound();
    }

    public function test_admin_can_retry_failed_provisioning_job(): void
    {
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer);
        $job = ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 2,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
            'processed_at' => now(),
            'last_error' => 'provider timeout',
        ]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post("/admin/provisioning-jobs/{$job->id}/retry")
            ->assertRedirect('/admin/provisioning-jobs');

        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertSame(2, $job->attempts);
        $this->assertNull($job->last_error);
        $this->assertNull($job->processed_at);
    }

    public function test_admin_cannot_retry_processed_provisioning_job(): void
    {
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer);
        $job = ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'processed',
            'attempts' => 1,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
            'processed_at' => now(),
        ]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from('/admin/provisioning-jobs')
            ->post("/admin/provisioning-jobs/{$job->id}/retry")
            ->assertRedirect('/admin/provisioning-jobs')
            ->assertSessionHasErrors('provisioning_job');

        $this->assertSame('processed', $job->refresh()->status);
    }

    public function test_admin_can_see_provisioning_job_runtime_state(): void
    {
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer);
        $availableAt = now()->addMinute()->startOfSecond();
        $processedAt = now()->subMinutes(2)->startOfSecond();

        ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'pending',
            'attempts' => 2,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
            'available_at' => $availableAt,
            'processed_at' => $processedAt,
            'last_error' => 'provider timeout, waiting for retry',
        ]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin/provisioning-jobs')
            ->assertOk()
            ->assertSee('Available')
            ->assertSee('Processed')
            ->assertSee($availableAt->toDateTimeString())
            ->assertSee($processedAt->toDateTimeString())
            ->assertSee('provider timeout, waiting for retry');
    }

    public function test_admin_can_view_provisioning_job_execution_logs(): void
    {
        $customer = $this->customerUser();
        $service = $this->serviceFor($customer);
        $job = ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 1,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => ['product' => ['code' => $service->product_code]],
            'last_error' => 'Provider returned HTTP 401.',
        ]);
        DB::table('provisioning_execution_logs')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'provisioning_job_id' => $job->id,
            'service_id' => $service->id,
            'provider_account_id' => null,
            'action' => 'provision_service',
            'driver' => 'generic_http',
            'endpoint' => 'https://provider-a.example.test/api/provision',
            'status' => 'failed',
            'http_status' => 401,
            'duration_ms' => 42,
            'error_code' => 'provider_http_error',
            'error_message' => 'Provider returned HTTP 401.',
            'request_payload' => json_encode(['api_key' => '***redacted***', 'product' => ['code' => $service->product_code]]),
            'response_payload' => json_encode(['message' => 'unauthorized']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get("/admin/provisioning-jobs/{$job->id}")
            ->assertOk()
            ->assertSee('Execution Logs')
            ->assertSee('provider_http_error')
            ->assertSee('https://provider-a.example.test/api/provision')
            ->assertSee('***redacted***')
            ->assertDontSee('provider-secret-1234');
    }

    private function serviceFor(User $user, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'proxy-vn-30d',
            'name' => 'Vietnam Proxy 30 Days',
            'type' => 'proxy',
            'status' => 'active',
        ]);
        $order = Order::factory()->for($user)->create();
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

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
