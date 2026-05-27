<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_order_page_from_order_index(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('admin-order-customer@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        Product::factory()->create([
            'name' => 'Admin Order Proxy',
            'status' => 'active',
            'price_amount' => 99000,
        ]);
        Product::factory()->create([
            'name' => 'Draft Admin Product',
            'status' => 'draft',
            'price_amount' => 149000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('Create Order')
            ->assertSee('href="/admin/orders/create"', false);

        $this->actingAs($admin)
            ->get('/admin/orders/create')
            ->assertOk()
            ->assertSee('Create Order')
            ->assertSee('admin-order-customer@example.test')
            ->assertSee('Admin Order Proxy')
            ->assertSee('Draft Admin Product - Draft (activate before ordering)')
            ->assertSee('data-product-status="draft"', false)
            ->assertSee('disabled', false)
            ->assertSee('Wallet will be debited immediately');
    }

    public function test_admin_can_create_paid_order_for_customer_wallet(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('admin-checkout@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $product = Product::factory()->create([
            'code' => 'admin-checkout-proxy',
            'name' => 'Admin Checkout Proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
        ]);

        $response = $this->actingAs($admin)->post('/admin/orders', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $response->assertRedirect("/admin/orders/{$order->id}");

        $this->assertSame('paid', $order->status);
        $this->assertSame(99000, $order->total_amount);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $customer->id,
            'currency' => 'VND',
            'balance_amount' => 151000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_code' => 'admin-checkout-proxy',
            'unit_amount' => 99000,
        ]);

        $service = Service::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('pending_provision', $service->status);
        $this->assertDatabaseHas('provisioning_jobs', [
            'order_id' => $order->id,
            'service_id' => $service->id,
            'status' => 'pending',
            'type' => 'provision_service',
        ]);

        $this->assertSame('order', LedgerEntry::where('source_id', $order->id)->firstOrFail()->source_type);

        $audit = AdminAuditLog::where('action', 'admin_order_created')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($customer->id, $audit->metadata['customer_id']);
        $this->assertSame($product->id, $audit->metadata['product_id']);
    }

    public function test_admin_create_order_rejects_insufficient_wallet_balance(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('low-wallet@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $product = Product::factory()->create([
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
        ]);

        $this->actingAs($admin)->from('/admin/orders/create')->post('/admin/orders', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ])->assertRedirect('/admin/orders/create')
            ->assertSessionHasErrors('wallet');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Service::count());
        $this->assertSame(0, ProvisioningJob::count());
    }

    public function test_order_create_permission_is_required(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('admin.access'));
        $viewer->givePermissionTo(Permission::findOrCreate('orders.view'));

        $this->actingAs($viewer)->get('/admin/orders')->assertOk();
        $this->actingAs($viewer)->get('/admin/orders/create')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/orders', [])->assertForbidden();
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
