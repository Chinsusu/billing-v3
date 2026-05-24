<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProviderActionJobQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_creates_pending_provider_action_job(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [$account, $service] = $this->providerBackedService();

        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'suspend', "service-suspend:{$service->id}:2026-05-24T09:00:00.000000Z", [
            'expired_at' => now()->toISOString(),
        ]);

        $this->assertSame($service->id, $job->service_id);
        $this->assertSame($service->user_id, $job->user_id);
        $this->assertSame($account->id, $job->provider_account_id);
        $this->assertSame('suspend', $job->action);
        $this->assertSame('pending', $job->status);
        $this->assertSame(0, $job->attempts);
        $this->assertSame(3, $job->max_attempts);
        $this->assertSame("service-suspend:{$service->id}:2026-05-24T09:00:00.000000Z", $job->idempotency_key);
        $this->assertSame(['context' => ['expired_at' => '2026-05-24T09:00:00.000000Z']], $job->payload);
        $this->assertNull($job->available_at);
        $this->assertNull($job->processed_at);
        $this->assertNull($job->last_error);
    }

    public function test_dispatcher_is_idempotent_by_idempotency_key(): void
    {
        [, $service] = $this->providerBackedService();
        $dispatcher = app(ProviderActionJobDispatcher::class);

        $first = $dispatcher->enqueue($service, 'sync', "service-sync:{$service->id}:manual");
        $second = $dispatcher->enqueue($service, 'sync', "service-sync:{$service->id}:manual");

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('provider_action_jobs', 1);
    }

    private function providerBackedService(array $serviceOverrides = []): array
    {
        $customer = User::factory()->create();
        $account = ProvisioningProviderAccount::create([
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
            'request_template' => [],
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'status',
            'response_config_path' => 'data.config',
        ]);
        $product = Product::factory()->create([
            'code' => 'vps-provider-a-30d',
            'name' => 'Provider A VPS 30 Days',
            'type' => 'vps',
            'status' => 'active',
            'provider_account_id' => $account->id,
            'provider_plan_code' => 'A1',
            'provider_region' => 'sgp1',
            'provider_suspend_path' => '/api/services/{external_id}/suspend',
            'provider_cancel_path' => '/api/services/{external_id}/cancel',
            'provider_sync_path' => '/api/services/{external_id}',
            'lifecycle_source' => 'provider_response',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
        ]);
        $order = Order::factory()->for($customer)->create();
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
        ]);
        $provider = [
            'account_id' => $account->id,
            'account_slug' => $account->slug,
            'driver' => $account->driver,
            'plan_code' => 'A1',
            'region' => 'sgp1',
            'provision_path' => '/api/accounts/main/provision',
            'renew_path' => null,
            'suspend_path' => '/api/services/{external_id}/suspend',
            'cancel_path' => '/api/services/{external_id}/cancel',
            'sync_path' => '/api/services/{external_id}',
            'options' => [],
        ];
        $service = Service::factory()->for($customer)->for($order)->for($item, 'orderItem')->for($product)->create($serviceOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'external_id' => 'provider-service-123',
            'expires_at' => now()->addDays(10),
            'meta' => [
                'duration_days' => 30,
                'provider' => $provider,
                'lifecycle_policy' => [
                    'source' => 'provider_response',
                    'unit' => 'day',
                    'count' => 30,
                    'provider_lifecycle_path' => null,
                    'ordered_at_path' => null,
                    'expires_at_path' => 'data.expires_at',
                    'date_format' => 'iso8601',
                    'timezone' => 'UTC',
                ],
            ],
        ]);

        return [$account, $service];
    }
}
