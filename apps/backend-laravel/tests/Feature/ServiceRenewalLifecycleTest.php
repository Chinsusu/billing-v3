<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceRenewalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_renew_active_service_from_wallet(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDays(10),
            'meta' => ['duration_days' => 30],
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}");

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertTrue($service->expires_at->isSameSecond($now->copy()->addDays(40)));
        $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 99000,
            'balance_after' => 101000,
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
        ]);
        $this->assertCount(1, $service->meta['renewals']);
        $this->assertSame(99000, $service->meta['renewals'][0]['amount']);
        $this->assertSame('VND', $service->meta['renewals'][0]['currency']);
    }

    public function test_renewal_uses_current_product_price(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor(
            $customer,
            ['status' => 'active', 'expires_at' => $now->copy()->addDays(5)],
            ['price_amount' => 120000],
            ['unit_amount' => 99000, 'subtotal_amount' => 99000]
        );

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $this->assertSame(80000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
            'amount' => 120000,
        ]);
    }

    public function test_calendar_month_renewal_uses_no_overflow_from_service_snapshot(): void
    {
        $now = Carbon::parse('2026-01-20 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => Carbon::parse('2026-01-31 09:00:00'),
            'meta' => [
                'duration_days' => 30,
                'lifecycle_policy' => [
                    'source' => 'local_policy',
                    'unit' => 'calendar_month',
                    'count' => 1,
                    'provider_lifecycle_path' => null,
                    'ordered_at_path' => null,
                    'expires_at_path' => null,
                    'date_format' => 'iso8601',
                    'timezone' => 'UTC',
                ],
            ],
        ]);

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $service->refresh();
        $this->assertTrue($service->expires_at->isSameSecond(Carbon::parse('2026-02-28 09:00:00')));
        $this->assertSame('2026-02-28T09:00:00.000000Z', $service->meta['renewals'][0]['new_expires_at']);
    }

    public function test_renewal_falls_back_to_order_item_snapshot_when_product_is_missing(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor(
            $customer,
            [
                'status' => 'active',
                'expires_at' => $now->copy()->addDays(5),
                'product_id' => null,
            ],
            ['price_amount' => 120000],
            ['unit_amount' => 77000, 'subtotal_amount' => 77000]
        );

        $this->actingAs($customer)->post("/services/{$service->id}/renew");

        $this->assertSame(123000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
            'amount' => 77000,
        ]);
    }

    public function test_insufficient_wallet_balance_does_not_renew_service(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDays(10),
        ]);
        $oldExpiresAt = $service->expires_at->copy();

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('wallet');

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(1000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_customer_cannot_renew_another_users_service(): void
    {
        $owner = $this->customerUser();
        $service = $this->serviceFor($owner, [
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);
        $oldExpiresAt = $service->expires_at->copy();
        $other = User::factory()->create();
        $other->assignRole('customer');
        Wallet::factory()->for($other)->create(['balance_amount' => 200000]);

        $this->actingAs($other)->post("/services/{$service->id}/renew")->assertNotFound();

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_expired_services_cannot_be_renewed(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $service = $this->serviceFor($customer, [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('service');

        $this->assertSame('expired', $service->refresh()->status);
        $this->assertSame(200000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_provider_backed_renewal_calls_provider_and_uses_provider_expiry_before_debiting_wallet(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        [$account, $service] = $this->providerBackedServiceFor($customer, [
            'expires_at' => $now->copy()->addDays(10),
        ]);

        Http::fake(function ($request) {
            $this->assertSame(200000, Wallet::firstOrFail()->balance_amount);

            return Http::response([
                'status' => 'success',
                'data' => [
                    'expires_at' => '2026-07-24T09:00:00+00:00',
                ],
            ]);
        });

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}");

        $service->refresh();
        $this->assertTrue($service->expires_at->isSameSecond(Carbon::parse('2026-07-24 09:00:00')));
        $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'source_type' => 'service_renewal',
            'source_id' => $service->id,
            'amount' => 99000,
        ]);
        $this->assertSame('2026-07-24T09:00:00.000000Z', $service->meta['renewals'][0]['new_expires_at']);
        $this->assertDatabaseHas('provisioning_execution_logs', [
            'service_id' => $service->id,
            'provider_account_id' => $account->id,
            'action' => 'provider_service_renew',
            'status' => 'success',
            'http_status' => 200,
        ]);

        Http::assertSent(function ($request) use ($service): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://provider-a.example.test/api/services/provider-service-123/renew'
                && $request->hasHeader('Authorization', 'Bearer provider-secret-1234')
                && $payload['action'] === 'renew'
                && $payload['idempotency_key'] === "service-renewal:{$service->id}:2026-06-03T09:00:00.000000Z"
                && $payload['service_id'] === $service->id
                && $payload['external_id'] === 'provider-service-123'
                && $payload['product']['plan_code'] === 'A1';
        });
    }

    public function test_provider_renewal_failure_does_not_debit_wallet_or_extend_service(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        [$account, $service] = $this->providerBackedServiceFor($customer, [
            'expires_at' => $now->copy()->addDays(10),
        ]);
        $oldExpiresAt = $service->expires_at->copy();

        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/renew' => Http::response([
                'message' => 'provider rejected renewal',
            ], 422),
        ]);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/renew")
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHasErrors('provider');

        $this->assertTrue($service->refresh()->expires_at->isSameSecond($oldExpiresAt));
        $this->assertSame(200000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
        $this->assertDatabaseHas('provisioning_execution_logs', [
            'service_id' => $service->id,
            'provider_account_id' => $account->id,
            'action' => 'provider_service_renew',
            'status' => 'failed',
            'http_status' => 422,
            'error_code' => 'provider_action_http_error',
        ]);
    }

    public function test_services_expire_command_marks_overdue_active_services_expired(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        $overdue = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->subMinute(),
        ]);
        $future = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->addDay(),
        ], ['code' => 'proxy-vn-60d']);

        $this->artisan('services:expire')
            ->expectsOutput('Expired 1 services. Failed 0 services.')
            ->assertExitCode(0);

        $this->assertSame('expired', $overdue->refresh()->status);
        $this->assertSame($now->toISOString(), $overdue->meta['expired_at']);
        $this->assertSame('active', $future->refresh()->status);
    }

    public function test_services_expire_command_suspends_provider_service_before_marking_expired(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        [$account, $overdue] = $this->providerBackedServiceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->subMinute(),
        ]);

        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/suspend' => Http::response([
                'status' => 'success',
            ]),
        ]);

        $this->artisan('services:expire')
            ->expectsOutput('Expired 1 services. Failed 0 services.')
            ->assertExitCode(0);

        $this->assertSame('expired', $overdue->refresh()->status);
        $this->assertSame($now->toISOString(), $overdue->meta['expired_at']);
        $this->assertDatabaseHas('provisioning_execution_logs', [
            'service_id' => $overdue->id,
            'provider_account_id' => $account->id,
            'action' => 'provider_service_suspend',
            'status' => 'success',
            'http_status' => 200,
        ]);

        Http::assertSent(function ($request) use ($overdue): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://provider-a.example.test/api/services/provider-service-123/suspend'
                && $request->hasHeader('Authorization', 'Bearer provider-secret-1234')
                && $payload['action'] === 'suspend'
                && $payload['idempotency_key'] === "service-suspend:{$overdue->id}:2026-05-24T09:00:00.000000Z";
        });
    }

    public function test_services_expire_command_keeps_service_active_when_provider_suspend_fails(): void
    {
        $now = Carbon::parse('2026-05-24 09:00:00');
        $this->travelTo($now);
        $customer = $this->customerUser();
        [$account, $overdue] = $this->providerBackedServiceFor($customer, [
            'status' => 'active',
            'expires_at' => $now->copy()->subMinute(),
        ]);

        Http::fake([
            'https://provider-a.example.test/api/services/provider-service-123/suspend' => Http::response([
                'message' => 'cannot suspend',
            ], 500),
        ]);

        $this->artisan('services:expire')
            ->expectsOutput('Expired 0 services. Failed 1 services.')
            ->assertExitCode(1);

        $this->assertSame('active', $overdue->refresh()->status);
        $this->assertArrayNotHasKey('expired_at', $overdue->meta ?? []);
        $this->assertDatabaseHas('provisioning_execution_logs', [
            'service_id' => $overdue->id,
            'provider_account_id' => $account->id,
            'action' => 'provider_service_suspend',
            'status' => 'failed',
            'http_status' => 500,
            'error_code' => 'provider_action_http_error',
        ]);
    }

    private function serviceFor(
        User $user,
        array $serviceOverrides = [],
        array $productOverrides = [],
        array $itemOverrides = []
    ): Service {
        $product = Product::factory()->create($productOverrides + [
            'code' => 'proxy-vn-30d',
            'name' => 'Vietnam Proxy 30 Days',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);
        $order = Order::factory()->for($user)->create([
            'currency' => 'VND',
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create($itemOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'unit_amount' => 99000,
            'subtotal_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create($serviceOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'meta' => ['duration_days' => 30],
        ]);
    }

    private function providerBackedServiceFor(User $user, array $serviceOverrides = []): array
    {
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
        $provider = [
            'account_id' => $account->id,
            'account_slug' => $account->slug,
            'driver' => $account->driver,
            'plan_code' => 'A1',
            'region' => 'sgp1',
            'provision_path' => '/api/accounts/main/provision',
            'renew_path' => '/api/services/{external_id}/renew',
            'suspend_path' => '/api/services/{external_id}/suspend',
            'cancel_path' => '/api/services/{external_id}/cancel',
            'sync_path' => '/api/services/{external_id}',
            'options' => ['size' => 'small'],
        ];
        $lifecyclePolicy = [
            'source' => 'provider_response',
            'unit' => 'day',
            'count' => 30,
            'provider_lifecycle_path' => null,
            'ordered_at_path' => 'data.ordered_at',
            'expires_at_path' => 'data.expires_at',
            'date_format' => 'iso8601',
            'timezone' => 'UTC',
        ];
        $service = $this->serviceFor($user, $serviceOverrides + [
            'status' => 'active',
            'external_id' => 'provider-service-123',
            'meta' => [
                'duration_days' => 30,
                'lifecycle_policy' => $lifecyclePolicy,
                'provider' => $provider,
            ],
        ], [
            'provider_account_id' => $account->id,
            'provider_plan_code' => 'A1',
            'provider_region' => 'sgp1',
            'provider_provision_path' => '/api/accounts/main/provision',
            'provider_renew_path' => '/api/services/{external_id}/renew',
            'provider_suspend_path' => '/api/services/{external_id}/suspend',
            'provider_cancel_path' => '/api/services/{external_id}/cancel',
            'provider_sync_path' => '/api/services/{external_id}',
            'provider_options' => ['size' => 'small'],
            'lifecycle_source' => 'provider_response',
            'provider_lifecycle_ordered_at_path' => 'data.ordered_at',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
        ]);

        return [$account, $service];
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
