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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    public function test_internal_executor_runs_provider_lifecycle_lookup_after_generic_http_provisioning(): void
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
            'provider_provision_path' => '/api/accounts/main/provision',
            'lifecycle_source' => 'provider_lookup',
            'lifecycle_unit' => 'calendar_month',
            'lifecycle_count' => 1,
            'provider_lifecycle_path' => '/api/services/{external_id}',
            'provider_lifecycle_ordered_at_path' => 'data.ordered_at',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'UTC',
        ]);

        Http::fake([
            'https://provider-a.example.test/api/accounts/main/provision' => Http::response([
                'data' => [
                    'id' => 'provider-service-123',
                    'status' => 'active',
                    'config' => ['ip' => '203.0.113.10'],
                ],
            ]),
            'https://provider-a.example.test/api/services/provider-service-123' => Http::response([
                'data' => [
                    'ordered_at' => '2026-05-24T09:00:00+00:00',
                    'expires_at' => '2026-06-24T09:00:00+00:00',
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
                'ordered_at' => '2026-05-24T09:00:00+00:00',
                'expires_at' => '2026-06-24T09:00:00+00:00',
            ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://provider-a.example.test/api/services/provider-service-123'
            && $request->hasHeader('Authorization', 'Bearer provider-secret-1234'));

        $this->assertSame(1, DB::table('provisioning_execution_logs')
            ->where('provisioning_job_id', $job->id)
            ->where('action', 'provider_lifecycle_lookup')
            ->where('status', 'success')
            ->count());
    }

    public function test_internal_executor_runs_cloudmini_v3_with_route_fallback_and_operation_polling(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $user = User::factory()->create();
        ProvisioningProviderAccount::create([
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
        $primaryAccount = ProvisioningProviderAccount::create([
            'slug' => 'cloudmini-prod-1',
            'name' => 'Cloudmini Prod 1',
            'driver' => 'cloudmini_v3',
            'base_url' => 'https://cloudmini-prod-1.example.test',
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'cloudmini-secret-1111',
            'api_key_last_four' => '1111',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'resource_snapshot.id',
            'response_status_path' => 'state',
        ]);
        $fallbackAccount = ProvisioningProviderAccount::create([
            'slug' => 'cloudmini-prod-2',
            'name' => 'Cloudmini Prod 2',
            'driver' => 'cloudmini_v3',
            'base_url' => 'https://cloudmini-prod-2.example.test',
            'auth_type' => 'header',
            'auth_header_name' => 'X-API-Key',
            'api_key' => 'cloudmini-secret-2222',
            'api_key_last_four' => '2222',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'resource_snapshot.id',
            'response_status_path' => 'state',
        ]);
        $product = Product::factory()->create([
            'code' => 'cloudmini-res-30d',
            'name' => 'Cloudmini Residential 30 Days',
            'type' => 'proxy',
            'status' => 'active',
            'provider_options' => [
                'kind' => 'residential',
                'protocol' => 'socks5',
                'speed_limit_mbps' => 20,
                'bandwidth_limit_mb' => 0,
                'reserve_capacity' => false,
            ],
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
        $job = ProvisioningJob::create([
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
                    'config' => [],
                    'lifecycle_policy' => ['source' => 'local_policy'],
                    'provider' => [
                        'driver' => 'cloudmini_v3',
                        'options' => $product->provider_options,
                        'routes' => [
                            [
                                'provider_account_id' => $primaryAccount->id,
                                'account_slug' => $primaryAccount->slug,
                                'priority' => 10,
                                'weight' => 100,
                                'billing_group_id' => 'vn-residential',
                                'node_selector_type' => 'auto',
                                'node_name' => null,
                                'options' => [],
                            ],
                            [
                                'provider_account_id' => $fallbackAccount->id,
                                'account_slug' => $fallbackAccount->slug,
                                'priority' => 20,
                                'weight' => 100,
                                'billing_group_id' => 'vn-residential',
                                'node_selector_type' => 'auto',
                                'node_name' => null,
                                'options' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'available_at' => now(),
        ]);

        Http::fake([
            'https://cloudmini-prod-1.example.test/api/v3/inventory/groups?kind=residential' => Http::response([
                'success' => true,
                'data' => [[
                    'id' => (string) Str::uuid(),
                    'billing_group_id' => 'vn-residential',
                    'sell_state' => 'exhausted',
                    'allocatable_units' => 0,
                    'free_ip_count' => 0,
                ]],
            ]),
            'https://cloudmini-prod-2.example.test/api/v3/inventory/groups?kind=residential' => Http::response([
                'success' => true,
                'data' => [[
                    'id' => '00000000-0000-4000-8000-000000000002',
                    'billing_group_id' => 'vn-residential',
                    'sell_state' => 'sellable',
                    'allocatable_units' => 3,
                    'free_ip_count' => 3,
                ]],
            ]),
            'https://cloudmini-prod-2.example.test/api/v3/inventory/nodes?kind=residential&group_id=00000000-0000-4000-8000-000000000002' => Http::response([
                'success' => true,
                'data' => [[
                    'id' => '00000000-0000-4000-8000-000000000102',
                    'group_id' => '00000000-0000-4000-8000-000000000002',
                    'name' => 'node-hcm-02',
                    'sell_state' => 'sellable',
                    'allocatable_units' => 2,
                    'free_ip_count' => 2,
                ]],
            ]),
            'https://cloudmini-prod-2.example.test/api/v3/proxies' => Http::response([
                'success' => true,
                'data' => [
                    'resource' => ['id' => 'proxy-2', 'status' => 'provisioning'],
                    'operation' => ['id' => 'operation-2', 'state' => 'accepted', 'resource_id' => 'proxy-2'],
                ],
            ], 202),
            'https://cloudmini-prod-2.example.test/api/v3/operations/operation-2' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 'operation-2',
                    'state' => 'succeeded',
                    'resource_id' => 'proxy-2',
                    'resource_snapshot' => [
                        'id' => 'proxy-2',
                        'status' => 'running',
                        'host' => '103.28.32.78',
                        'port_socks' => 14496,
                        'username' => 'u_proxy',
                        'password' => 'generated-password',
                        'connection_uri' => 'socks5://u_proxy:generated-password@103.28.32.78:14496',
                    ],
                ],
            ]),
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer secret-token',
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'processed',
                'external_id' => 'proxy-2',
                'config' => [
                    'id' => 'proxy-2',
                    'status' => 'running',
                    'host' => '103.28.32.78',
                    'port_socks' => 14496,
                    'username' => 'u_proxy',
                    'password' => 'generated-password',
                    'connection_uri' => 'socks5://u_proxy:generated-password@103.28.32.78:14496',
                ],
            ]);

        Http::assertSent(function ($request) use ($service): bool {
            $payload = $request->data();

            return $request->url() === 'https://cloudmini-prod-2.example.test/api/v3/proxies'
                && $request->hasHeader('X-API-Key', 'cloudmini-secret-2222')
                && $request->hasHeader('Idempotency-Key', "service-provision:{$service->id}")
                && $payload['kind'] === 'residential'
                && $payload['group_id'] === '00000000-0000-4000-8000-000000000002'
                && $payload['node_id'] === '00000000-0000-4000-8000-000000000102'
                && $payload['protocol'] === 'socks5'
                && $payload['speed_limit_mbps'] === 20
                && $payload['external_ref'] === $service->id;
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
                    'lifecycle_policy' => [
                        'source' => $product->lifecycle_source,
                        'unit' => $product->lifecycle_unit,
                        'count' => $product->lifecycle_count,
                        'provider_lifecycle_path' => $product->provider_lifecycle_path,
                        'ordered_at_path' => $product->provider_lifecycle_ordered_at_path,
                        'expires_at_path' => $product->provider_lifecycle_expires_at_path,
                        'date_format' => $product->provider_lifecycle_date_format,
                        'timezone' => $product->provider_lifecycle_timezone,
                    ],
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
