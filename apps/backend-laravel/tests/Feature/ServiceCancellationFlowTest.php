<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningProviderAccount;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceCancellationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_local_service_immediately(): void
    {
        $customer = $this->customerUser('local-cancel@example.test');
        $service = $this->serviceFor($customer, ['status' => 'active']);

        $this->actingAs($customer)
            ->from("/services/{$service->id}")
            ->post("/services/{$service->id}/cancel", [
                'mode' => 'immediate',
                'reason' => 'No longer needed',
            ])
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHas('status', 'Service cancelled.');

        $service->refresh();
        $this->assertSame('cancelled', $service->status);
        $this->assertNotNull(data_get($service->meta, 'cancelled_at'));
        $this->assertDatabaseHas('service_cancellations', [
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'mode' => 'immediate',
            'status' => 'completed',
            'reason' => 'No longer needed',
        ]);
        $this->assertSame(0, ProviderActionJob::count());
    }

    public function test_customer_provider_backed_cancel_queues_provider_action_once(): void
    {
        $customer = $this->customerUser('provider-cancel@example.test');
        [, $service] = $this->providerBackedService($customer);

        $this->actingAs($customer)
            ->post("/services/{$service->id}/cancel", [
                'mode' => 'immediate',
                'reason' => 'Cancel provider service',
            ])
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHas('status', 'Provider cancellation queued.');

        $this->actingAs($customer)
            ->post("/services/{$service->id}/cancel", [
                'mode' => 'immediate',
                'reason' => 'Duplicate request',
            ])
            ->assertRedirect("/services/{$service->id}");

        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame(1, ProviderActionJob::count());
        $job = ProviderActionJob::firstOrFail();
        $this->assertSame('cancel', $job->action);
        $this->assertSame('pending', $job->status);
        $this->assertSame("service-cancel:{$service->id}:customer-request", $job->idempotency_key);
        $this->assertSame(1, DB::table('service_cancellations')->where('service_id', $service->id)->count());
        $this->assertDatabaseHas('service_cancellations', [
            'service_id' => $service->id,
            'status' => 'queued',
            'provider_action_job_id' => $job->id,
        ]);
    }

    public function test_customer_can_schedule_period_end_cancellation(): void
    {
        $customer = $this->customerUser('period-cancel@example.test');
        $service = $this->serviceFor($customer, [
            'status' => 'active',
            'expires_at' => now()->addDays(12),
        ]);

        $this->actingAs($customer)
            ->post("/services/{$service->id}/cancel", [
                'mode' => 'period_end',
                'reason' => 'Cancel later',
            ])
            ->assertRedirect("/services/{$service->id}")
            ->assertSessionHas('status', 'Cancellation scheduled for period end.');

        $service->refresh();
        $this->assertSame('active', $service->status);
        $this->assertSame('period_end', data_get($service->meta, 'cancellation.mode'));
        $this->assertDatabaseHas('service_cancellations', [
            'service_id' => $service->id,
            'mode' => 'period_end',
            'status' => 'scheduled',
        ]);
    }

    public function test_customer_cannot_cancel_another_users_or_non_active_service(): void
    {
        $owner = $this->customerUser('owner-cancel@example.test');
        $other = $this->customerUser('other-cancel@example.test');
        $service = $this->serviceFor($owner, ['status' => 'active']);
        $expiredService = $this->serviceFor($other, ['status' => 'expired']);

        $this->actingAs($other)
            ->post("/services/{$service->id}/cancel", ['mode' => 'immediate'])
            ->assertNotFound();

        $this->actingAs($other)
            ->from("/services/{$expiredService->id}")
            ->post("/services/{$expiredService->id}/cancel", ['mode' => 'immediate'])
            ->assertRedirect("/services/{$expiredService->id}")
            ->assertSessionHasErrors('service');

        $this->assertSame('expired', $expiredService->fresh()->status);
    }

    public function test_admin_can_credit_refund_for_cancelled_service(): void
    {
        $admin = $this->adminUser('refund-admin@example.test');
        $customer = $this->customerUser('refund-customer@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 100000]);
        $service = $this->serviceFor($customer, ['status' => 'cancelled']);

        $this->actingAs($admin)
            ->post("/admin/services/{$service->id}/refund-credit", [
                'amount' => 25000,
                'currency' => 'VND',
                'reason' => 'Unused service credit',
                'reference' => 'REFUND-001',
            ])
            ->assertRedirect("/admin/services/{$service->id}")
            ->assertSessionHas('status', 'Refund credit recorded.');

        $this->assertSame(125000, $wallet->fresh()->balance_amount);
        $entry = LedgerEntry::firstOrFail();
        $this->assertSame('credit', $entry->direction);
        $this->assertSame('service_refund', $entry->source_type);
        $this->assertSame($service->id, $entry->source_id);
        $this->assertSame("service-refund:{$service->id}:REFUND-001", $entry->idempotency_key);
        $this->assertSame($admin->id, $entry->meta['actor_id']);
        $this->assertSame('refund-admin@example.test', $entry->meta['actor_email']);
    }

    private function serviceFor(User $user, array $overrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'cancel-proxy-30d',
            'name' => 'Cancel Proxy',
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

    private function providerBackedService(User $user): array
    {
        $account = ProvisioningProviderAccount::create([
            'slug' => 'cancel-provider',
            'name' => 'Cancel Provider',
            'driver' => 'generic_http',
            'base_url' => 'https://provider.example.test',
            'provision_path' => '/provision',
            'auth_type' => 'bearer',
            'api_key' => 'provider-secret-1234',
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => [],
            'response_external_id_path' => 'id',
            'response_status_path' => 'status',
        ]);
        $service = $this->serviceFor($user, [
            'status' => 'active',
            'external_id' => 'provider-service-cancel',
            'meta' => [
                'provider' => [
                    'account_id' => $account->id,
                    'account_slug' => $account->slug,
                    'driver' => $account->driver,
                    'cancel_path' => '/services/{external_id}/cancel',
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

    private function adminUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => $email]);
        $admin->assignRole('super_admin');

        return $admin;
    }
}
