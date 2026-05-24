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
use Illuminate\Support\Facades\Http;
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

    public function test_work_command_processes_suspend_job_and_marks_service_expired(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'suspend', "service-suspend:{$service->id}:2026-05-24T09:00:00.000000Z", [
            'expired_at' => now()->toISOString(),
        ]);
        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/suspend' => Http::response(['status' => 'success']),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=1 failed=0.')
            ->assertExitCode(0);

        $this->assertSame('expired', $service->refresh()->status);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $service->meta['expired_at']);
        $job->refresh();
        $this->assertSame('processed', $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertNotNull($job->processed_at);
        $this->assertNull($job->last_error);
    }

    public function test_work_command_requeues_failed_job_with_backoff_when_attempts_remain(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'suspend', "service-suspend:{$service->id}:2026-05-24T09:00:00.000000Z");
        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/suspend' => Http::response(['message' => 'provider down'], 500),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=0 failed=1.')
            ->assertExitCode(0);

        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertSame('Provider suspend action returned HTTP 500.', $job->last_error);
        $this->assertTrue($job->available_at->greaterThan(now()));
        $this->assertNull($job->processed_at);
        $this->assertSame('active', $service->refresh()->status);
    }

    public function test_work_command_marks_failed_when_max_attempts_are_reached(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'suspend', "service-suspend:{$service->id}:2026-05-24T09:00:00.000000Z");
        $job->forceFill(['attempts' => 2, 'max_attempts' => 3])->save();
        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/suspend' => Http::response(['message' => 'provider down'], 500),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=0 failed=1.')
            ->assertExitCode(0);

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame(3, $job->attempts);
        $this->assertSame('Provider suspend action returned HTTP 500.', $job->last_error);
        $this->assertNotNull($job->processed_at);
        $this->assertSame('active', $service->refresh()->status);
    }

    public function test_work_command_processes_sync_job_and_updates_service_from_provider(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'sync', "service-sync:{$service->id}:manual");
        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123' => Http::response([
                'status' => 'active',
                'data' => ['expires_at' => '2026-07-24T09:00:00+00:00'],
            ]),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=1 failed=0.')
            ->assertExitCode(0);

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertTrue($service->expires_at->isSameSecond(Carbon::parse('2026-07-24 09:00:00')));
        $this->assertSame('processed', $job->refresh()->status);
    }

    public function test_work_command_processes_cancel_job_and_marks_service_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'cancel', "service-cancel:{$service->id}:manual", [
            'cancelled_at' => now()->toISOString(),
        ]);
        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/cancel' => Http::response(['status' => 'success']),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=1 failed=0.')
            ->assertExitCode(0);

        $this->assertSame('cancelled', $service->refresh()->status);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $service->meta['cancelled_at']);
        $this->assertSame('processed', $job->refresh()->status);
    }

    public function test_recover_stuck_command_requeues_stale_processing_jobs_below_max_attempts(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'sync', "service-sync:{$service->id}:manual");
        $job->forceFill([
            'status' => 'processing',
            'attempts' => 1,
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $this->artisan('provider-actions:recover-stuck')
            ->expectsOutput('Provider action jobs requeued=1 failed=0.')
            ->assertExitCode(0);

        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertSame(1, $job->attempts);
        $this->assertNull($job->available_at);
        $this->assertNull($job->processed_at);
        $this->assertSame('Recovered stale processing job.', $job->last_error);
    }

    public function test_recover_stuck_command_fails_stale_processing_jobs_at_max_attempts(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        [, $service] = $this->providerBackedService();
        $job = app(ProviderActionJobDispatcher::class)->enqueue($service, 'sync', "service-sync:{$service->id}:manual");
        $job->forceFill([
            'status' => 'processing',
            'attempts' => 3,
            'max_attempts' => 3,
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $this->artisan('provider-actions:recover-stuck')
            ->expectsOutput('Provider action jobs requeued=0 failed=1.')
            ->assertExitCode(0);

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertSame(3, $job->attempts);
        $this->assertNull($job->available_at);
        $this->assertNotNull($job->processed_at);
        $this->assertSame('Stale processing job exceeded max attempts.', $job->last_error);
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
