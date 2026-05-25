<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_customer_to_reseller_and_reseller_lists_only_assigned_customers(): void
    {
        $admin = $this->superAdmin('s38-admin@example.test');
        $reseller = $this->reseller('s38-reseller@example.test');
        $customer = $this->customer('s38-customer@example.test');
        $otherCustomer = $this->customer('s38-other@example.test');

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/reseller", ['reseller_id' => $reseller->id])
            ->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertSame($reseller->id, User::findOrFail($customer->id)->reseller_id);

        $this->actingAs($reseller)
            ->get('/reseller/customers')
            ->assertOk()
            ->assertSee('s38-customer@example.test')
            ->assertDontSee('s38-other@example.test');

        $this->assertSame(1, AdminAuditLog::where('action', 'customer_reseller_assigned')->count());
    }

    public function test_reseller_price_override_is_used_for_checkout(): void
    {
        $admin = $this->superAdmin('s38-price-admin@example.test');
        $reseller = $this->reseller('s38-price-reseller@example.test');
        $customer = $this->customer('s38-price-customer@example.test');
        $customer->forceFill(['reseller_id' => $reseller->id])->save();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $product = Product::factory()->create([
            'code' => 's38-proxy',
            'name' => 'S38 Proxy',
            'status' => 'active',
            'price_amount' => 100000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        $this->actingAs($admin)
            ->post('/admin/reseller-price-overrides', [
                'reseller_id' => $reseller->id,
                'product_id' => $product->id,
                'price_amount' => 75000,
            ])
            ->assertRedirect('/admin/reseller-price-overrides');

        $this->assertDatabaseHas('reseller_price_overrides', [
            'reseller_id' => $reseller->id,
            'product_id' => $product->id,
            'price_amount' => 75000,
        ]);

        $this->actingAs($customer)
            ->post("/products/{$product->id}/order")
            ->assertRedirect();

        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(75000, $order->total_amount);
        $this->assertSame(75000, $order->items()->firstOrFail()->unit_amount);
        $this->assertSame(125000, Wallet::where('user_id', $customer->id)->firstOrFail()->balance_amount);
        $this->assertSame(1, AdminAuditLog::where('action', 'reseller_price_override_upserted')->count());
    }

    private function superAdmin(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return $this->userWithRole('super_admin', $email);
    }

    private function reseller(string $email): User
    {
        return $this->userWithRole('reseller', $email);
    }

    private function customer(string $email): User
    {
        return $this->userWithRole('customer', $email);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
