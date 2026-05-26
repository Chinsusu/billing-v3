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
            ->assertSee('data-lifecycle-source-select', false)
            ->assertSee('data-lifecycle-unit-select', false)
            ->assertSee('data-lifecycle-unit-field', false)
            ->assertSee('data-lifecycle-count-field', false)
            ->assertSee('data-lifecycle-count-input', false)
            ->assertSee('data-lifecycle-duration-field', false)
            ->assertSee('data-provider-response-field', false)
            ->assertSee('data-provider-lookup-field', false)
            ->assertDontSee('Stable SKU used by API, orders, and reports.')
            ->assertDontSee('Only active products appear in the customer catalog.')
            ->assertDontSee('Fallback duration for reports and local day-based products.')
            ->assertDontSee('Use provider lookup when dates must be fetched after external_id exists.')
            ->assertDontSee('Keep this concise; it is shown to customers before purchase.')
            ->assertDontSee('--product-form-help-min-height', false)
            ->assertDontSee('.product-form-field:not(:has(.field-help))::after', false)
            ->assertSeeInOrder([
                'Product identity',
                'Pricing & lifecycle',
                'Provisioning provider',
                'Auto-renew Policy',
                'Provider lifecycle response',
            ]);
    }

    public function test_admin_can_save_calendar_month_product_without_choosing_duration_days(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->post('/admin/products', [
            'code' => 'vps-calendar-monthly',
            'name' => 'Calendar Monthly VPS',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 199000,
            'currency' => 'VND',
            'description' => 'Calendar monthly plan',
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'calendar_month',
            'lifecycle_count' => 1,
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'vps-calendar-monthly')->firstOrFail();
        $this->assertSame('calendar_month', $product->lifecycle_unit);
        $this->assertSame(1, $product->lifecycle_count);
        $this->assertSame(30, $product->duration_days);

        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'code' => 'vps-calendar-monthly',
            'name' => 'Calendar Monthly VPS Updated',
            'type' => 'vps',
            'status' => 'active',
            'price_amount' => 219000,
            'currency' => 'VND',
            'description' => 'Two calendar month plan',
            'lifecycle_source' => 'local_policy',
            'lifecycle_unit' => 'calendar_month',
            'lifecycle_count' => 2,
        ])->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertSame(2, $product->lifecycle_count);
        $this->assertSame(60, $product->duration_days);
    }

    public function test_admin_can_save_provider_lifecycle_product_without_local_policy_inputs(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->post('/admin/products', [
            'code' => 'provider-response-plan',
            'name' => 'Provider Response Plan',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'description' => 'Dates are returned in the provider response.',
            'lifecycle_source' => 'provider_response',
            'provider_lifecycle_ordered_at_path' => 'data.ordered_at',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'UTC',
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'provider-response-plan')->firstOrFail();
        $this->assertSame('provider_response', $product->lifecycle_source);
        $this->assertSame('day', $product->lifecycle_unit);
        $this->assertSame(30, $product->lifecycle_count);
        $this->assertSame(30, $product->duration_days);
        $this->assertNull($product->provider_lifecycle_path);

        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'code' => 'provider-response-plan',
            'name' => 'Provider Lookup Plan',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 109000,
            'currency' => 'VND',
            'description' => 'Dates are fetched after external_id exists.',
            'lifecycle_source' => 'provider_lookup',
            'provider_lifecycle_path' => '/api/services/{external_id}',
            'provider_lifecycle_ordered_at_path' => 'data.ordered_at',
            'provider_lifecycle_expires_at_path' => 'data.expires_at',
            'provider_lifecycle_date_format' => 'iso8601',
            'provider_lifecycle_timezone' => 'UTC',
        ])->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertSame('provider_lookup', $product->lifecycle_source);
        $this->assertSame('/api/services/{external_id}', $product->provider_lifecycle_path);
        $this->assertSame(30, $product->duration_days);
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
