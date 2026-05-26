<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_admin_create_product_page_uses_scannable_form_sections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('product-form-shell', false)
            ->assertSee('product-form-grid', false)
            ->assertSee('product-form-section--identity', false)
            ->assertSee('product-form-actions', false)
            ->assertSeeInOrder([
                'Product identity',
                'Pricing & lifecycle',
                'Provisioning provider',
                'Auto-renew Policy',
                'Provider lifecycle response',
            ]);
    }

    public function test_admin_can_store_provider_mapping_on_product(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
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
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/products', [
            'code' => 'vps-provider-a-30d',
            'name' => 'Provider A VPS 30 Days',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
            'duration_days' => 30,
            'description' => 'Provider mapped VPS plan',
            'provider_account_id' => $accountId,
            'provider_plan_code' => 'A1',
            'provider_region' => 'sgp1',
            'provider_provision_path' => '/api/accounts/main/provision',
            'provider_options' => '{"size":"small","tags":["billing"]}',
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'vps-provider-a-30d')->firstOrFail();
        $this->assertSame($accountId, $product->provider_account_id);
        $this->assertSame('A1', $product->provider_plan_code);
        $this->assertSame('sgp1', $product->provider_region);
        $this->assertSame('/api/accounts/main/provision', $product->provider_provision_path);
        $this->assertSame(['size' => 'small', 'tags' => ['billing']], $product->provider_options);
    }

    public function test_customer_cannot_access_admin_product_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/products')->assertForbidden();
    }
}
