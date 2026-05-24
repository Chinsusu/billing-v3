<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\ServiceCancellation;
use App\Models\User;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceScheduledCancellationExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_due_local_period_end_cancellation_marks_service_cancelled(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = $this->customerUser('scheduled-local@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => now()->subMinute(),
            'meta' => [
                'cancellation' => [
                    'mode' => 'period_end',
                    'status' => 'scheduled',
                    'requested_at' => now()->subDays(3)->toISOString(),
                ],
            ],
        ]);
        $cancellation = $this->scheduledCancellation($service, $customer);

        $this->artisan('service-cancellations:process-scheduled')
            ->expectsOutput('Scheduled cancellations completed=1 queued=0 skipped=0 failed=0.')
            ->assertExitCode(0);

        $service->refresh();
        $cancellation->refresh();

        $this->assertSame('cancelled', $service->status);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $service->meta['cancelled_at']);
        $this->assertSame('completed', $service->meta['cancellation']['status']);
        $this->assertSame('period_end', $service->meta['cancellation']['mode']);
        $this->assertSame('scheduled_processor', $service->meta['cancellation']['completed_by']);
        $this->assertSame('completed', $cancellation->status);
        $this->assertNotNull($cancellation->completed_at);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $cancellation->meta['cancelled_at']);
        $this->assertSame(0, ProviderActionJob::count());

        $this->artisan('service-cancellations:process-scheduled')
            ->expectsOutput('Scheduled cancellations completed=0 queued=0 skipped=0 failed=0.')
            ->assertExitCode(0);
    }

    public function test_future_period_end_cancellation_is_not_processed(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = $this->customerUser('scheduled-future@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);
        $cancellation = $this->scheduledCancellation($service, $customer);

        $this->artisan('service-cancellations:process-scheduled')
            ->expectsOutput('Scheduled cancellations completed=0 queued=0 skipped=0 failed=0.')
            ->assertExitCode(0);

        $this->assertSame('active', $service->refresh()->status);
        $this->assertSame('scheduled', $cancellation->refresh()->status);
        $this->assertNull($cancellation->completed_at);
    }

    public function test_due_provider_backed_period_end_cancellation_queues_cancel_once(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = $this->customerUser('scheduled-provider@example.test');
        [, $service] = $this->providerBackedService($customer, [
            'status' => 'active',
            'expires_at' => now()->subMinute(),
        ]);
        $cancellation = $this->scheduledCancellation($service, $customer);

        $this->artisan('service-cancellations:process-scheduled')
            ->expectsOutput('Scheduled cancellations completed=0 queued=1 skipped=0 failed=0.')
            ->assertExitCode(0);

        $service->refresh();
        $cancellation->refresh();
        $job = ProviderActionJob::firstOrFail();

        $this->assertSame('active', $service->status);
        $this->assertSame('queued', $service->meta['cancellation']['status']);
        $this->assertSame('queued', $cancellation->status);
        $this->assertSame($job->id, $cancellation->provider_action_job_id);
        $this->assertSame('cancel', $job->action);
        $this->assertSame('pending', $job->status);
        $this->assertSame("service-cancel:{$service->id}:period-end:{$cancellation->id}", $job->idempotency_key);
        $this->assertSame($cancellation->id, $job->payload['context']['service_cancellation_id']);

        $this->artisan('service-cancellations:process-scheduled')
            ->expectsOutput('Scheduled cancellations completed=0 queued=0 skipped=0 failed=0.')
            ->assertExitCode(0);

        $this->assertSame(1, ProviderActionJob::count());
    }

    public function test_provider_cancel_action_success_completes_linked_scheduled_cancellation(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = $this->customerUser('scheduled-provider-complete@example.test');
        [, $service] = $this->providerBackedService($customer, [
            'status' => 'active',
            'expires_at' => now()->subMinute(),
        ]);
        $cancellation = $this->scheduledCancellation($service, $customer);
        $this->artisan('service-cancellations:process-scheduled')->assertExitCode(0);
        $job = ProviderActionJob::firstOrFail();

        Http::fake([
            'https://provider-scheduled.example.test/api/services/provider-scheduled-123/cancel' => Http::response(['status' => 'success']),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=1 failed=0.')
            ->assertExitCode(0);

        $service->refresh();
        $cancellation->refresh();

        $this->assertSame('cancelled', $service->status);
        $this->assertSame('completed', $service->meta['cancellation']['status']);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $service->meta['cancelled_at']);
        $this->assertSame('completed', $cancellation->status);
        $this->assertNotNull($cancellation->completed_at);
        $this->assertSame($job->id, $cancellation->provider_action_job_id);
        $this->assertSame($job->id, $cancellation->meta['provider_action_job_id']);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $cancellation->meta['provider_completed_at']);
    }

    public function test_scheduled_cancellation_processor_is_registered_with_scheduler(): void
    {
        $registry = app(ScheduledTaskRegistry::class);

        $this->assertSame('service-cancellations:process-scheduled --limit=50', $registry->commandFor('service_cancellations_process_scheduled'));

        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run service_cancellations_process_scheduled')
            ->assertExitCode(0);
    }

    private function scheduledCancellation(Service $service, User $customer): ServiceCancellation
    {
        return ServiceCancellation::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'mode' => 'period_end',
            'status' => 'scheduled',
            'reason' => 'End of term',
            'meta' => ['expires_at' => $service->expires_at?->toISOString()],
            'requested_at' => now()->subDays(3),
        ]);
    }

    private function serviceFor(User $user, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'scheduled-cancel-'.Str::lower(Str::random(8)),
            'name' => 'Scheduled Cancel Proxy',
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
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);
    }

    private function providerBackedService(User $user, array $serviceOverrides = []): array
    {
        $account = ProvisioningProviderAccount::create([
            'slug' => 'scheduled-provider',
            'name' => 'Scheduled Provider',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-scheduled.example.test',
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
        $service = $this->serviceFor($user, $serviceOverrides + [
            'external_id' => 'provider-scheduled-123',
            'meta' => [
                'provider' => [
                    'account_id' => $account->id,
                    'account_slug' => $account->slug,
                    'driver' => $account->driver,
                    'cancel_path' => '/api/services/{external_id}/cancel',
                ],
                'cancellation' => [
                    'mode' => 'period_end',
                    'status' => 'scheduled',
                ],
            ],
        ]);

        return [$account, $service];
    }

    private function customerUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
