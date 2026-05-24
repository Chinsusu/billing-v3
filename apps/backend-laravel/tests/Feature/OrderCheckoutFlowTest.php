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
