<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_orders_services_and_provisioning_jobs(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $product = Product::factory()->create([
            'name' => 'Basic VPS 30 Days',
            'code' => 'vps-basic-30d',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
        ]);
        $this->actingAs($customer)->post("/products/{$product->id}/order");
        $order = Order::firstOrFail();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Basic VPS 30 Days')
            ->assertSee($customer->email);

        $this->actingAs($admin)
            ->get('/admin/services')
            ->assertOk()
            ->assertSee('Basic VPS 30 Days')
            ->assertSee('pending_provision');

        $this->actingAs($admin)
            ->get('/admin/provisioning-jobs')
            ->assertOk()
            ->assertSee('provision_service')
            ->assertSee('pending')
            ->assertSee('vps-basic-30d');
    }

    public function test_customer_cannot_access_admin_operations_pages(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
        $this->actingAs($customer)->get('/admin/services')->assertForbidden();
        $this->actingAs($customer)->get('/admin/provisioning-jobs')->assertForbidden();
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
