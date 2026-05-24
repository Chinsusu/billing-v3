<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_wallet_credit_and_invoice_payment_enqueue_customer_notifications_once(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'bank-test-secret']);
        $customer = User::factory()->create(['email' => 'notify-customer@example.test']);
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        $intent = PaymentIntent::factory()->for($customer)->for($wallet)->create([
            'reference' => 'TOPUP-NOTIFY-001',
            'amount' => 150000,
            'currency' => 'VND',
        ]);
        $payload = [
            'reference' => 'TOPUP-NOTIFY-001',
            'amount' => 150000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-NOTIFY-001',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/bank/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_BILLING_SIGNATURE' => hash_hmac('sha256', $body, 'bank-test-secret'),
        ], $body)->assertOk()->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'wallet_credited',
            'recipient_email' => 'notify-customer@example.test',
            'source_type' => 'payment_intent',
            'source_id' => $intent->id,
            'idempotency_key' => "wallet-credited:{$intent->id}",
            'status' => 'pending',
        ]);

        $invoice = Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-NOTIFY-001',
            'total_amount' => 50000,
            'currency' => 'VND',
        ]);

        $this->actingAs($customer)
            ->post("/invoices/{$invoice->id}/pay")
            ->assertRedirect("/invoices/{$invoice->id}");

        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'invoice_paid',
            'recipient_email' => 'notify-customer@example.test',
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'idempotency_key' => "invoice-paid:{$invoice->id}",
            'status' => 'pending',
        ]);

        $this->actingAs($customer)->post("/invoices/{$invoice->id}/pay");
        $this->assertSame(1, DB::table('notification_events')->where('idempotency_key', "invoice-paid:{$invoice->id}")->count());
    }

    public function test_notification_send_command_marks_sent_and_retries_failures(): void
    {
        $sentId = $this->insertNotification([
            'type' => 'invoice_paid',
            'recipient_email' => 'sent@example.test',
            'subject' => 'Invoice paid',
            'body_text' => 'Your invoice was paid.',
            'idempotency_key' => 'notify-send-success',
        ]);

        Mail::shouldReceive('raw')->once()->withArgs(function (string $body, callable $callback): bool {
            return $body === 'Your invoice was paid.';
        });

        $this->artisan('notifications:send --limit=10')
            ->expectsOutput('Notifications sent=1 retried=0 failed=0.')
            ->assertExitCode(0);

        $sent = DB::table('notification_events')->where('id', $sentId)->first();
        $this->assertSame('sent', $sent->status);
        $this->assertSame(1, $sent->attempts);
        $this->assertNotNull($sent->sent_at);
        $this->assertNull($sent->last_error);

        $failedId = $this->insertNotification([
            'type' => 'wallet_credited',
            'recipient_email' => 'retry@example.test',
            'subject' => 'Wallet credited',
            'body_text' => 'Wallet credit failed to send.',
            'idempotency_key' => 'notify-send-retry',
            'max_attempts' => 2,
        ]);

        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->artisan('notifications:send --limit=10')
            ->expectsOutput('Notifications sent=0 retried=1 failed=0.')
            ->assertExitCode(0);

        $failed = DB::table('notification_events')->where('id', $failedId)->first();
        $this->assertSame('pending', $failed->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertSame('SMTP down', $failed->last_error);
        $this->assertNotNull($failed->available_at);
    }

    public function test_admin_can_filter_view_and_retry_failed_notifications(): void
    {
        $admin = $this->adminUser();
        $eventId = $this->insertNotification([
            'type' => 'provider_action_failed',
            'recipient_email' => 'ops@example.test',
            'subject' => 'Provider action failed',
            'body_text' => 'Provider suspend failed.',
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'Provider timeout',
            'idempotency_key' => 'provider-action-failed:test',
        ]);

        $this->actingAs($admin)
            ->get('/admin/notification-events?status=failed&type=provider_action_failed')
            ->assertOk()
            ->assertSee('provider_action_failed')
            ->assertSee('ops@example.test')
            ->assertSee('Provider timeout');

        $this->actingAs($admin)
            ->get("/admin/notification-events/{$eventId}")
            ->assertOk()
            ->assertSee('Provider suspend failed.')
            ->assertSee('provider-action-failed:test');

        $this->actingAs($admin)
            ->post("/admin/notification-events/{$eventId}/retry")
            ->assertRedirect("/admin/notification-events/{$eventId}");

        $event = DB::table('notification_events')->where('id', $eventId)->first();
        $this->assertSame('pending', $event->status);
        $this->assertNull($event->last_error);
        $this->assertNotNull($event->available_at);
    }

    public function test_service_expiry_warning_is_queued_once_and_scheduler_is_registered(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $customer = User::factory()->create(['email' => 'expiry@example.test']);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => now()->addHours(48),
        ]);

        Mail::shouldReceive('raw')->once();
        $this->artisan('notifications:send --limit=10')
            ->expectsOutput('Notifications sent=1 retried=0 failed=0.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'service_expiry_warning',
            'recipient_email' => 'expiry@example.test',
            'source_type' => 'service',
            'source_id' => $service->id,
            'idempotency_key' => "service-expiry-warning:{$service->id}:".$service->expires_at->toISOString(),
            'status' => 'sent',
        ]);

        $this->artisan('notifications:send --limit=10')
            ->expectsOutput('Notifications sent=0 retried=0 failed=0.')
            ->assertExitCode(0);
        $this->assertSame(1, DB::table('notification_events')->where('type', 'service_expiry_warning')->count());

        $this->assertSame('notifications:send --limit=50', app(\App\Services\Scheduler\ScheduledTaskRegistry::class)->commandFor('notifications_send'));
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run notifications_send')
            ->assertExitCode(0);
    }

    public function test_service_renewal_and_cancellation_enqueue_customer_notifications(): void
    {
        $customer = $this->customerUser('lifecycle@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($customer)
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}");

        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'service_renewed',
            'recipient_email' => 'lifecycle@example.test',
            'source_type' => 'service',
            'source_id' => $service->id,
            'status' => 'pending',
        ]);

        $cancelService = $this->serviceFor($customer, ['status' => 'active']);
        $this->actingAs($customer)
            ->post("/services/{$cancelService->id}/cancel", [
                'mode' => 'immediate',
                'reason' => 'No longer needed',
            ])
            ->assertRedirect("/services/{$cancelService->id}");

        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'service_cancellation_requested',
            'source_type' => 'service_cancellation',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'service_cancellation_completed',
            'source_type' => 'service',
            'source_id' => $cancelService->id,
            'status' => 'pending',
        ]);
    }

    public function test_provider_callback_unmatched_and_final_provider_action_failure_enqueue_operator_notifications(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $account = $this->providerAccount();
        $payload = [
            'event_id' => 'evt-notify-unmatched',
            'external_id' => 'missing-provider-service',
            'action' => 'sync',
            'status' => 'active',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', "/webhooks/providers/{$account->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PROVIDER_SIGNATURE' => hash_hmac('sha256', $body, 'callback-secret-1234'),
        ], $body)->assertAccepted();

        $callbackEvent = DB::table('provider_callback_events')->where('provider_event_id', 'evt-notify-unmatched')->first();
        $this->assertDatabaseHas('notification_events', [
            'type' => 'provider_callback_unmatched',
            'recipient_email' => config('mail.from.address'),
            'source_type' => 'provider_callback_event',
            'source_id' => $callbackEvent->id,
            'status' => 'pending',
        ]);

        [, $service] = $this->providerBackedService(User::factory()->create(['email' => 'provider-failure@example.test']));
        $job = ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $service->user_id,
            'provider_account_id' => $account->id,
            'action' => 'suspend',
            'status' => 'pending',
            'attempts' => 2,
            'max_attempts' => 3,
            'idempotency_key' => "service-suspend:{$service->id}:notify-failure",
            'payload' => ['context' => []],
        ]);
        Http::fake([
            'https://provider-notify.example.test/api/services/provider-notify-123/suspend' => Http::response(['message' => 'provider down'], 500),
        ]);

        $this->artisan('provider-actions:work --once')
            ->expectsOutput('Provider action jobs processed=0 failed=1.')
            ->assertExitCode(0);

        $this->assertSame('failed', $job->refresh()->status);
        $this->assertDatabaseHas('notification_events', [
            'type' => 'provider_action_failed',
            'recipient_email' => config('mail.from.address'),
            'source_type' => 'provider_action_job',
            'source_id' => $job->id,
            'status' => 'pending',
        ]);
    }

    private function insertNotification(array $overrides = []): string
    {
        $id = (string) Str::uuid();
        DB::table('notification_events')->insert($overrides + [
            'id' => $id,
            'user_id' => null,
            'channel' => 'email',
            'type' => 'test_notification',
            'recipient_email' => 'recipient@example.test',
            'subject' => 'Test notification',
            'body_text' => 'Test body',
            'source_type' => 'test',
            'source_id' => null,
            'idempotency_key' => 'test-notification:'.$id,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => now(),
            'sent_at' => null,
            'last_error' => null,
            'payload' => json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function serviceFor(User $user, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'notify-product-'.Str::lower(Str::random(8)),
            'name' => 'Notify Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 50000,
            'currency' => 'VND',
            'duration_days' => 30,
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'day',
            'lifecycle_count' => 30,
        ]);
        $order = Order::factory()->for($user)->create();
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'unit_amount' => 50000,
            'subtotal_amount' => 50000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create($overrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'meta' => [
                'duration_days' => 30,
                'lifecycle_policy' => [
                    'source' => 'local_policy',
                    'unit' => 'day',
                    'count' => 30,
                ],
            ],
        ]);
    }

    private function providerAccount(): ProvisioningProviderAccount
    {
        return ProvisioningProviderAccount::create([
            'slug' => 'notify-provider-'.Str::lower(Str::random(8)),
            'name' => 'Notify Provider',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-notify.example.test',
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

    private function providerBackedService(User $customer): array
    {
        $account = $this->providerAccount();
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'external_id' => 'provider-notify-123',
            'meta' => [
                'provider' => [
                    'account_id' => $account->id,
                    'account_slug' => $account->slug,
                    'driver' => $account->driver,
                    'suspend_path' => '/api/services/{external_id}/suspend',
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

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'notification-admin@example.test']);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
