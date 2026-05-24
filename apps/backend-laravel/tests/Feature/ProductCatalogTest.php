<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_catalog_only_lists_active_products(): void
    {
        Product::factory()->create(['name' => 'Active Proxy', 'status' => 'active']);
        Product::factory()->create(['name' => 'Draft VPS', 'status' => 'draft']);

        $this->get('/products')
            ->assertOk()
            ->assertSee('Active Proxy')
            ->assertDontSee('Draft VPS');
    }

    public function test_admin_can_create_update_and_archive_product(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $create = $this->actingAs($admin)->post('/admin/products', [
            'code' => 'proxy-vn-30d',
            'name' => 'VN Proxy 30 Days',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => 'Shared proxy test plan',
        ]);

        $product = Product::where('code', 'proxy-vn-30d')->firstOrFail();
        $create->assertRedirect('/admin/products');
        $this->assertSame(99000, $product->price_amount);

        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'code' => 'proxy-vn-30d',
            'name' => 'VN Proxy 30 Days Updated',
            'type' => 'proxy',
            'status' => 'draft',
            'price_amount' => 109000,
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => 'Updated plan',
        ])->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'VN Proxy 30 Days Updated',
            'status' => 'draft',
            'price_amount' => 109000,
        ]);

        $this->actingAs($admin)->delete("/admin/products/{$product->id}")
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'archived',
        ]);
    }

    public function test_customer_cannot_access_admin_product_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/products')->assertForbidden();
    }
}
