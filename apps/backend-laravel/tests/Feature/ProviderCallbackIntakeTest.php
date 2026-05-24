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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderCallbackIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_valid_signed_cancel_callback_reconciles_service_job_and_cancellation_once(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = User::factory()->create();
        $account = $this->providerAccount();
        $service = $this->providerBackedService($customer, $account, [
            'status' => 'active',
            'external_id' => 'provider-callback-123',
        ]);
        $job = ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => $account->id,
            'action' => 'cancel',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => "service-cancel:{$service->id}:callback",
            'payload' => ['context' => []],
        ]);
        $cancellation = ServiceCancellation::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'provider_action_job_id' => $job->id,
            'mode' => 'period_end',
            'status' => 'queued',
            'reason' => 'End of term',
            'meta' => ['provider_action_job_id' => $job->id],
            'requested_at' => now()->subDay(),
        ]);
        $payload = [
            'event_id' => 'evt-cancel-001',
            'external_id' => 'provider-callback-123',
            'action' => 'cancel',
            'status' => 'cancelled',
            'secret_token' => 'provider-sensitive-token',
        ];

        $this->signedProviderCallback($account, $payload)
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $service->refresh();
        $job->refresh();
        $cancellation->refresh();
        $event = DB::table('provider_callback_events')->first();

        $this->assertSame('cancelled', $service->status);
        $this->assertSame('2026-05-24T09:00:00.000000Z', $service->meta['cancelled_at']);
        $this->assertSame('completed', $service->meta['cancellation']['status']);
        $this->assertSame('processed', $job->status);
        $this->assertNotNull($job->processed_at);
        $this->assertNull($job->last_error);
        $this->assertSame('completed', $cancellation->status);
        $this->assertNotNull($cancellation->completed_at);
        $this->assertSame($job->id, $cancellation->provider_action_job_id);
        $this->assertSame($account->id, $event->provider_account_id);
        $this->assertSame($service->id, $event->service_id);
        $this->assertSame($job->id, $event->provider_action_job_id);
        $this->assertSame('evt-cancel-001', $event->provider_event_id);
        $this->assertSame('provider-callback-123', $event->external_id);
        $this->assertSame('cancel', $event->action);
        $this->assertSame('cancelled', $event->provider_status);
        $this->assertSame('valid', $event->signature_status);
        $this->assertSame('processed', $event->processing_status);
        $this->assertSame('***redacted***', json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR)['secret_token']);

        $this->signedProviderCallback($account, $payload)
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(1, DB::table('provider_callback_events')->count());
        $this->assertSame('cancelled', $service->refresh()->status);
    }

    public function test_invalid_provider_callback_signature_is_rejected_without_audit_event(): void
    {
        $account = $this->providerAccount();

        $this->rawProviderCallback($account, [
            'event_id' => 'evt-invalid-signature',
            'external_id' => 'missing',
            'action' => 'cancel',
            'status' => 'cancelled',
        ], 'bad-signature')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'invalid_signature');

        $this->assertSame(0, DB::table('provider_callback_events')->count());
    }

    public function test_failed_cancel_callback_does_not_complete_service_or_cancellation(): void
    {
        $customer = User::factory()->create();
        $account = $this->providerAccount();
        $service = $this->providerBackedService($customer, $account, [
            'status' => 'active',
            'external_id' => 'provider-cancel-failed-123',
        ]);
        $job = ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => $account->id,
            'action' => 'cancel',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => "service-cancel:{$service->id}:failed-callback",
            'payload' => ['context' => []],
        ]);
        $cancellation = ServiceCancellation::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'provider_action_job_id' => $job->id,
            'mode' => 'period_end',
            'status' => 'queued',
            'reason' => 'End of term',
            'meta' => ['provider_action_job_id' => $job->id],
            'requested_at' => now()->subDay(),
        ]);

        $this->signedProviderCallback($account, [
            'event_id' => 'evt-cancel-failed-001',
            'external_id' => 'provider-cancel-failed-123',
            'action' => 'cancel',
            'status' => 'failed',
        ])->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame('active', $service->refresh()->status);
        $this->assertSame('failed', $job->refresh()->status);
        $this->assertSame('queued', $cancellation->refresh()->status);
        $this->assertNull($cancellation->completed_at);
    }

    public function test_pending_cancel_callback_does_not_process_job_or_complete_cancellation(): void
    {
        $customer = User::factory()->create();
        $account = $this->providerAccount();
        $service = $this->providerBackedService($customer, $account, [
            'status' => 'active',
            'external_id' => 'provider-cancel-pending-123',
        ]);
        $job = ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => $account->id,
            'action' => 'cancel',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => "service-cancel:{$service->id}:pending-callback",
            'payload' => ['context' => []],
        ]);
        $cancellation = ServiceCancellation::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'provider_action_job_id' => $job->id,
            'mode' => 'period_end',
            'status' => 'queued',
            'reason' => 'End of term',
            'meta' => ['provider_action_job_id' => $job->id],
            'requested_at' => now()->subDay(),
        ]);

        $this->signedProviderCallback($account, [
            'event_id' => 'evt-cancel-pending-001',
            'external_id' => 'provider-cancel-pending-123',
            'action' => 'cancel',
            'status' => 'pending',
        ])->assertOk()->assertJsonPath('status', 'processed');

        $this->assertSame('active', $service->refresh()->status);
        $this->assertSame('pending', $job->refresh()->status);
        $this->assertSame('queued', $cancellation->refresh()->status);
        $this->assertNull($cancellation->completed_at);
    }

    public function test_unmatched_provider_callback_is_audited_without_service_mutation(): void
    {
        $account = $this->providerAccount();

        $this->signedProviderCallback($account, [
            'event_id' => 'evt-unmatched-001',
            'external_id' => 'provider-missing-service',
            'action' => 'sync',
            'status' => 'active',
        ])
            ->assertAccepted()
            ->assertJsonPath('status', 'unmatched');

        $event = DB::table('provider_callback_events')->first();

        $this->assertNull($event->service_id);
        $this->assertNull($event->provider_action_job_id);
        $this->assertSame('evt-unmatched-001', $event->provider_event_id);
        $this->assertSame('provider-missing-service', $event->external_id);
        $this->assertSame('unmatched', $event->processing_status);
    }

    public function test_admin_can_configure_callback_secret_and_paths_without_rendering_secret(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/provisioning-provider-accounts', [
            'slug' => 'callback-admin',
            'name' => 'Callback Admin Provider',
            'driver' => 'generic_http',
            'base_url' => 'https://callback-admin.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-api-secret',
            'enabled' => '1',
            'timeout_seconds' => 15,
            'request_template' => '{}',
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'status',
            'response_config_path' => '',
            'callback_secret' => 'callback-secret-1234',
            'callback_event_id_path' => 'event.id',
            'callback_external_id_path' => 'resource.external_id',
            'callback_action_path' => 'event.action',
            'callback_status_path' => 'event.status',
        ])->assertRedirect('/admin/provisioning-provider-accounts');

        $account = ProvisioningProviderAccount::where('slug', 'callback-admin')->firstOrFail();
        $this->assertSame('callback-secret-1234', $account->callback_secret);
        $this->assertSame('1234', $account->callback_secret_last_four);
        $this->assertSame('event.id', $account->callback_event_id_path);
        $this->assertSame('resource.external_id', $account->callback_external_id_path);
        $this->assertSame('event.action', $account->callback_action_path);
        $this->assertSame('event.status', $account->callback_status_path);

        $this->actingAs($admin)
            ->get("/admin/provisioning-provider-accounts/{$account->id}/edit")
            ->assertOk()
            ->assertSee('Configured ...1234')
            ->assertDontSee('callback-secret-1234');
    }

    public function test_admin_service_runbook_shows_provider_callback_history(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create();
        $account = $this->providerAccount();
        $service = $this->providerBackedService($customer, $account, [
            'external_id' => 'provider-runbook-123',
        ]);
        $this->signedProviderCallback($account, [
            'event_id' => 'evt-runbook-001',
            'external_id' => 'provider-runbook-123',
            'action' => 'sync',
            'status' => 'active',
        ])->assertOk();

        $this->actingAs($admin)
            ->get("/admin/services/{$service->id}")
            ->assertOk()
            ->assertSee('Provider Callbacks')
            ->assertSee('evt-runbook-001')
            ->assertSee('processed')
            ->assertSee('sync');
    }

    private function providerAccount(): ProvisioningProviderAccount
    {
        return ProvisioningProviderAccount::create([
            'slug' => 'callback-provider-'.Str::lower(Str::random(8)),
            'name' => 'Callback Provider',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-callback.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-secret-1234',
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'data.id',
            'response_status_path' => 'status',
            'callback_secret' => 'callback-secret-1234',
            'callback_secret_last_four' => '1234',
            'callback_event_id_path' => 'event_id',
            'callback_external_id_path' => 'external_id',
            'callback_action_path' => 'action',
            'callback_status_path' => 'status',
        ]);
    }

    private function providerBackedService(User $user, ProvisioningProviderAccount $account, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'callback-product-'.Str::lower(Str::random(8)),
            'name' => 'Callback Product',
            'type' => 'vps',
            'status' => 'active',
            'provider_account_id' => $account->id,
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
            'external_id' => 'provider-callback-123',
            'meta' => [
                'provider' => [
                    'account_id' => $account->id,
                    'account_slug' => $account->slug,
                    'driver' => $account->driver,
                    'cancel_path' => '/api/services/{external_id}/cancel',
                    'sync_path' => '/api/services/{external_id}',
                ],
            ],
        ]);
    }

    private function signedProviderCallback(ProvisioningProviderAccount $account, array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'callback-secret-1234');

        return $this->rawProviderCallback($account, $payload, $signature);
    }

    private function rawProviderCallback(ProvisioningProviderAccount $account, array $payload, string $signature)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', "/webhooks/providers/{$account->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PROVIDER_SIGNATURE' => $signature,
        ], $body);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
