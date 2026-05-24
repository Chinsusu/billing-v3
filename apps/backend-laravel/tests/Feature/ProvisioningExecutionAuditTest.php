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
use Tests\TestCase;

class ProvisioningExecutionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_executor_records_successful_generic_http_execution_log(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $job = $this->genericHttpJob();

        Http::fake([
            'https://provider-a.example.test/api/provision' => Http::response([
                'data' => [
                    'id' => 'provider-service-123',
                    'status' => 'active',
                    'config' => ['ip' => '203.0.113.10'],
                ],
                'api_key' => 'response-secret-value',
            ]),
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer secret-token',
        ])->assertOk();

        $log = DB::table('provisioning_execution_logs')->where('provisioning_job_id', $job->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame('generic_http', $log->driver);
        $this->assertSame('https://provider-a.example.test/api/provision', $log->endpoint);
        $this->assertSame(200, $log->http_status);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
        $this->assertStringNotContainsString('provider-secret-1234', $this->payloadString($log->request_payload));
        $this->assertStringNotContainsString('response-secret-value', $this->payloadString($log->response_payload));
        $this->assertStringContainsString('***redacted***', $this->payloadString($log->response_payload));
    }

    public function test_internal_executor_records_failed_generic_http_execution_log(): void
    {
        config(['services.internal_provisioning.token' => 'secret-token']);
        $job = $this->genericHttpJob();

        Http::fake([
            'https://provider-a.example.test/api/provision' => Http::response([
                'message' => 'invalid api key',
            ], 401),
        ]);

        $this->postJson("/internal/provisioning/jobs/{$job->id}/execute", [], [
            'Authorization' => 'Bearer secret-token',
        ])
            ->assertStatus(422)
            ->assertJson(['message' => 'Provider returned HTTP 401.']);

        $log = DB::table('provisioning_execution_logs')->where('provisioning_job_id', $job->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertSame(401, $log->http_status);
        $this->assertSame('provider_http_error', $log->error_code);
        $this->assertStringContainsString('Provider returned HTTP 401.', $log->error_message);
        $this->assertStringNotContainsString('provider-secret-1234', $this->payloadString($log->request_payload));
    }

    private function genericHttpJob(): ProvisioningJob
    {
        $user = User::factory()->create();
        $providerAccount = ProvisioningProviderAccount::create([
            'slug' => 'provider-a-main',
            'name' => 'Provider A Main',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-a.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-secret-1234',
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => ['api_key' => 'template-secret-value'],
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'data.status',
            'response_config_path' => 'data.config',
        ]);
        $product = Product::factory()->create([
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
                'product' => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'type' => $product->type,
                    'duration_days' => $product->duration_days,
                    'config' => [],
                    'provider' => [
                        'account_id' => $providerAccount->id,
                        'account_slug' => $providerAccount->slug,
                        'driver' => $providerAccount->driver,
                        'plan_code' => 'A1',
                        'region' => 'sgp1',
                        'provision_path' => '/api/provision',
                        'options' => ['token' => 'option-secret-value'],
                    ],
                ],
            ],
            'available_at' => now(),
        ]);
    }

    private function payloadString(mixed $payload): string
    {
        return is_string($payload) ? $payload : json_encode($payload);
    }
}
