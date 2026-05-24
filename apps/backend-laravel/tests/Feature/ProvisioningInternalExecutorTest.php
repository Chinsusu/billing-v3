<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProvisioningInternalExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_executor_rejects_invalid_token(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $job = $this->provisioningJobForProvider([
            'slug' => 'sandbox',
            'driver' => 'sandbox',
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertForbidden();
    }

    public function test_internal_executor_runs_sandbox_driver(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $job = $this->provisioningJobForProvider([
            'slug' => 'sandbox',
            'driver' => 'sandbox',
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer secret-token',
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'processed',
                'external_id' => "sandbox-proxy-{$job->service_id}",
                'config' => [],
            ]);
    }

    public function test_internal_executor_runs_generic_http_driver_with_account_secret(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $job = $this->provisioningJobForProvider([
            'slug' => 'provider-a-main',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-a.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-secret-1234',
            'api_key_last_four' => '1234',
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'data.status',
            'response_config_path' => 'data.config',
        ], [
            'provider_plan_code' => 'A1',
            'provider_region' => 'sgp1',
            'provider_provision_path' => '/api/accounts/main/provision',
            'provider_options' => ['size' => 'small'],
        ]);

        Http::fake([
            'https://provider-a.example.test/api/accounts/main/provision' => Http::response([
                'data' => [
                    'id' => 'provider-service-123',
                    'status' => 'active',
                    'config' => ['ip' => '203.0.113.10'],
                ],
            ]),
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer secret-token',
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'processed',
                'external_id' => 'provider-service-123',
                'config' => ['ip' => '203.0.113.10'],
            ]);

        Http::assertSent(function ($request) use ($job): bool {
            $payload = $request->data();

            return $request->url() === 'https://provider-a.example.test/api/accounts/main/provision'
                && $request->hasHeader('Authorization', 'Bearer provider-secret-1234')
                && $payload['idempotency_key'] === $job->idempotency_key
                && $payload['product']['plan_code'] === 'A1'
                && $payload['product']['region'] === 'sgp1'
                && $payload['product']['options'] === ['size' => 'small'];
        });
    }

    private function provisioningJobForProvider(array $providerOverrides, array $productOverrides = []): ProvisioningJob
    {
        $user = User::factory()->create();
        $providerAccount = ProvisioningProviderAccount::create($providerOverrides + [
            'slug' => 'sandbox',
            'name' => 'Sandbox Provisioning',
            'driver' => 'sandbox',
            'auth_type' => 'none',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'external_id',
            'response_status_path' => 'status',
        ]);
        $product = Product::factory()->create($productOverrides + [
            'code' => 'proxy-vn-30d',
            'name' => 'Vietnam Proxy 30 Days',
            'type' => 'proxy',
            'status' => 'active',
            'provider_account_id' => $providerAccount->id,
        ]);
        $order = Order::factory()->for($user)->create();
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);
        $service = Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);

        return ProvisioningJob::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'user_id' => $user->id,
            'type' => 'provision_service',
            'status' => 'processing',
            'attempts' => 1,
            'idempotency_key' => "service-provision:{$service->id}",
            'payload' => [
                'action' => 'provision',
                'order_id' => $order->id,
                'service_id' => $service->id,
                'user_id' => $user->id,
                'product' => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'type' => $product->type,
                    'duration_days' => $product->duration_days,
                    'config' => $product->config ?? [],
                    'provider' => [
                        'account_id' => $providerAccount->id,
                        'account_slug' => $providerAccount->slug,
                        'driver' => $providerAccount->driver,
                        'plan_code' => $product->provider_plan_code,
                        'region' => $product->provider_region,
                        'provision_path' => $product->provider_provision_path ?: $providerAccount->provision_path,
                        'options' => $product->provider_options ?? [],
                    ],
                ],
            ],
            'available_at' => now(),
        ]);
    }
}
