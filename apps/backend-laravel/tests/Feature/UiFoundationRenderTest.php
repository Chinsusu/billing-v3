<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiFoundationRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_pages_render_shared_ui_foundation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create(['email' => 'ui-customer@example.test']);
        $customer->assignRole('customer');

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('app-shell--customer', false)
            ->assertSee('customer-desktop-foundation', false)
            ->assertSee('--customer-desktop-content-max', false)
            ->assertSee('page-header', false)
            ->assertSee('stat-card', false)
            ->assertSee('empty-state', false);

        $this->actingAs($customer)
            ->get('/api-keys')
            ->assertOk()
            ->assertSee('form-section', false)
            ->assertSee('empty-state', false);
    }

    public function test_product_catalog_uses_scannable_product_cards(): void
    {
        Product::factory()->create([
            'name' => 'Vietnam Proxy 30 Days',
            'code' => 'proxy-vn-30d',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        $this->get('/products')
            ->assertOk()
            ->assertSee('app-shell--customer', false)
            ->assertSee('product-grid', false)
            ->assertSee('product-card', false)
            ->assertSee('Vietnam Proxy 30 Days');
    }

    public function test_admin_pages_render_operations_ui_foundation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'ui-admin@example.test']);
        $admin->assignRole('super_admin');
        $customer = User::factory()->create(['email' => 'ui-table-customer@example.test']);
        $customer->assignRole('customer');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('app-shell--admin', false)
            ->assertSee('admin-desktop-foundation', false)
            ->assertSee('--admin-desktop-content-max', false)
            ->assertSee('ops-dashboard-grid', false)
            ->assertSee('stat-card', false)
            ->assertSee('quick-action-grid', false);

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('filter-bar', false)
            ->assertSee('data-table', false);

        $this->actingAs($admin)
            ->get('/admin/customers?search=no-match')
            ->assertOk()
            ->assertSee('empty-state', false);
    }

    public function test_customer_footer_uses_plain_current_year(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('&copy; '.date('Y').' Billing v3.', false)
            ->assertDontSee('UTC', false);
    }
}
