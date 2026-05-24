<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_order_active_product_from_wallet(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $product = Product::factory()->create([
            'name' => 'Vietnam Proxy 30 Days',
            'code' => 'proxy-vn-30d',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        $response = $this->actingAs($customer)->post("/products/{$product->id}/order");

        $order = Order::firstOrFail();
        $service = Service::firstOrFail();
        $job = ProvisioningJob::firstOrFail();

        $response->assertRedirect("/orders/{$order->id}");
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('paid', $order->status);
        $this->assertSame(99000, $order->total_amount);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);

        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 99000,
            'balance_after' => 101000,
            'source_type' => 'order',
            'source_id' => $order->id,
            'idempotency_key' => "order-payment:{$order->id}",
        ]);

        $this->assertSame($order->id, $service->order_id);
        $this->assertSame($customer->id, $service->user_id);
        $this->assertSame($product->id, $service->product_id);
        $this->assertSame('pending_provision', $service->status);
        $this->assertSame($order->id, $job->order_id);
        $this->assertSame($service->id, $job->service_id);
        $this->assertSame('pending', $job->status);
        $this->assertSame('provision_service', $job->type);
        $this->assertSame("service-provision:{$service->id}", $job->idempotency_key);
        $this->assertSame('proxy-vn-30d', $job->payload['product']['code']);

        $this->actingAs($customer)
            ->get("/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Vietnam Proxy 30 Days')
            ->assertSee('paid');

        $this->actingAs($customer)
            ->get('/services')
            ->assertOk()
            ->assertSee('Vietnam Proxy 30 Days')
            ->assertSee('pending_provision');
    }

    public function test_checkout_snapshots_product_provider_mapping_into_provisioning_job(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 300000]);
        $accountId = (string) Str::uuid();
        DB::table('provisioning_provider_accounts')->insert([
            'id' => $accountId,
            'slug' => 'provider-a-main',
            'name' => 'Provider A Main',
            'driver' => 'generic_http',
            'base_url' => 'https://provider-a.example.test',
            'provision_path' => '/api/provision',
            'auth_type' => 'bearer',
            'auth_header_name' => null,
            'api_key' => app('encrypter')->encrypt('provider-secret-1234', false),
            'api_key_last_four' => '1234',
            'enabled' => true,
            'timeout_seconds' => 15,
            'request_template' => '{}',
            'response_external_id_path' => 'external_id',
            'response_status_path' => 'status',
            'response_config_path' => null,
            'created_by_id' => null,
            'updated_by_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product = Product::factory()->create([
            'name' => 'Provider A VPS 30 Days',
            'code' => 'vps-provider-a-30d',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
            'duration_days' => 30,
            'provider_account_id' => $accountId,
            'provider_plan_code' => 'A2',
            'provider_region' => 'sgp1',
            'provider_provision_path' => '/api/accounts/main/provision',
            'provider_options' => ['size' => 'small', 'backups' => true],
        ]);

        $this->actingAs($customer)->post("/products/{$product->id}/order");

        $job = ProvisioningJob::firstOrFail();
        $this->assertSame([
            'account_id' => $accountId,
            'account_slug' => 'provider-a-main',
            'driver' => 'generic_http',
            'plan_code' => 'A2',
            'region' => 'sgp1',
            'provision_path' => '/api/accounts/main/provision',
            'options' => ['size' => 'small', 'backups' => true],
        ], $job->payload['product']['provider']);
    }

    public function test_checkout_snapshots_lifecycle_policy_and_uses_calendar_month_no_overflow(): void
    {
        $this->travelTo('2026-01-31 09:00:00');
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 300000]);
        $product = Product::factory()->create([
            'name' => 'Calendar Monthly VPS',
            'code' => 'vps-calendar-monthly',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
            'duration_days' => 30,
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'calendar_month',
            'lifecycle_count' => 1,
        ]);

        $this->actingAs($customer)->post("/products/{$product->id}/order");

        $service = Service::firstOrFail();
        $job = ProvisioningJob::firstOrFail();
        $expectedPolicy = [
            'source' => 'local_policy',
            'unit' => 'calendar_month',
            'count' => 1,
            'provider_lifecycle_path' => null,
            'ordered_at_path' => null,
            'expires_at_path' => null,
            'date_format' => 'iso8601',
            'timezone' => 'UTC',
        ];

        $this->assertTrue($service->expires_at->isSameSecond(now()->addMonthNoOverflow()));
        $this->assertSame($expectedPolicy, $service->meta['lifecycle_policy']);
        $this->assertSame($expectedPolicy, $job->payload['product']['lifecycle_policy']);
    }

    public function test_checkout_with_insufficient_wallet_balance_rolls_back(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $product = Product::factory()->create([
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
        ]);

        $this->actingAs($customer)
            ->from('/products')
            ->post("/products/{$product->id}/order")
            ->assertRedirect('/products')
            ->assertSessionHasErrors('wallet');

        $this->assertSame(1000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Service::count());
        $this->assertSame(0, ProvisioningJob::count());
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_customer_cannot_order_inactive_product(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $product = Product::factory()->create(['status' => 'draft']);

        $this->actingAs($customer)->post("/products/{$product->id}/order")->assertNotFound();
    }

    public function test_customer_cannot_view_another_users_order_or_service(): void
    {
        $owner = $this->customerUser();
        Wallet::factory()->for($owner)->create(['balance_amount' => 200000]);
        $product = Product::factory()->create(['status' => 'active', 'price_amount' => 99000]);
        $this->actingAs($owner)->post("/products/{$product->id}/order");
        $order = Order::firstOrFail();

        $other = User::factory()->create();
        $other->assignRole('customer');

        $this->actingAs($other)->get("/orders/{$order->id}")->assertNotFound();
        $this->actingAs($other)->get('/services')->assertOk()->assertDontSee($product->name);
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
