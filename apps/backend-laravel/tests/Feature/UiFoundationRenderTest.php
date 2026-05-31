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
            ->assertSee('app-shell--vuexy', false)
            ->assertSee('layout-navbar-floating', false)
            ->assertSee('navbar-search', false)
            ->assertSee('user-avatar', false)
            ->assertSee('customer-desktop-foundation', false)
            ->assertSee('--customer-desktop-content-max', false)
            ->assertSee('sidebar-menu-icon', false)
            ->assertSee('data-menu-icon="dashboard"', false)
            ->assertSee('data-menu-icon="wallet"', false)
            ->assertSee('data-menu-icon="services"', false)
            ->assertSee('data-menu-icon="api-keys"', false)
            ->assertSee('fill="none"', false)
            ->assertSee('stroke="currentColor"', false)
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

    public function test_product_catalog_search_filters_by_name_code_or_type(): void
    {
        Product::factory()->create([
            'name' => 'Vietnam Proxy 30 Days',
            'code' => 'proxy-vn-30d',
            'type' => 'proxy',
            'status' => 'active',
        ]);
        Product::factory()->create([
            'name' => 'Singapore VPS Monthly',
            'code' => 'vps-sg-monthly',
            'type' => 'vps',
            'status' => 'active',
        ]);

        $this->get('/products?search=proxy-vn')
            ->assertOk()
            ->assertSee('Vietnam Proxy 30 Days')
            ->assertDontSee('Singapore VPS Monthly');

        $this->get('/products?search=vps')
            ->assertOk()
            ->assertSee('Singapore VPS Monthly')
            ->assertDontSee('Vietnam Proxy 30 Days');
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
            ->assertSee('app-shell--vuexy', false)
            ->assertSee('layout-navbar-floating', false)
            ->assertSee('navbar-search', false)
            ->assertSee('user-avatar', false)
            ->assertSee('admin-desktop-foundation', false)
            ->assertSee('--admin-desktop-content-max', false)
            ->assertSee('sidebar-menu-icon', false)
            ->assertSee('data-menu-icon="overview"', false)
            ->assertSee('data-menu-icon="payment-events"', false)
            ->assertSee('data-menu-icon="provider-accounts"', false)
            ->assertSee('data-menu-icon="ops-health"', false)
            ->assertSee('data-menu-icon="support-tickets"', false)
            ->assertSee('fill="none"', false)
            ->assertSee('stroke="currentColor"', false)
            ->assertSeeInOrder([
                'Admin Overview',
                'Resources',
                'Products',
                'Orders',
                'Services',
                'Financials',
                'Invoices',
                'Payment Events',
            ])
            ->assertSee('ops-dashboard-grid', false)
            ->assertSee('stat-card', false)
            ->assertSee('quick-action-grid', false);

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('customer-filter-panel', false)
            ->assertSee('admin-customer-search-options', false)
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

    public function test_vuexy_shell_overrides_link_hover_and_soft_button_states(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'ui-hover-admin@example.test']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('body.app-shell--vuexy .sidebar-menu-item:hover', false)
            ->assertSee('body.app-shell--vuexy .sidebar-menu-icon', false)
            ->assertSee('stroke-linecap: round;', false)
            ->assertSee('text-decoration: none;', false)
            ->assertSee('body.app-shell--vuexy .button.secondary.button-soft', false)
            ->assertSee('--sidebar-text', false)
            ->assertSee('--topbar-muted', false);
    }

    public function test_vuexy_topbar_actions_use_consistent_controls(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'ui-topbar-admin@example.test']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('topbar-action portal-switch', false)
            ->assertSee('theme-toggle-btn icon-button topbar-action', false)
            ->assertSee('button secondary button-compact topbar-action', false)
            ->assertSee('body.app-shell--vuexy .portal-switch', false);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('topbar-action portal-switch', false)
            ->assertSee('theme-toggle-btn icon-button topbar-action', false)
            ->assertSee('button secondary button-compact topbar-action', false);
    }

    public function test_desktop_shell_uses_full_width_content_area(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'ui-wide-admin@example.test']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('--admin-desktop-content-max: none;', false)
            ->assertSee('--admin-desktop-gutter: 20px;', false)
            ->assertSee('body.app-shell--admin .content-wrapper', false)
            ->assertSee('max-width: none;', false);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('--customer-desktop-content-max: none;', false)
            ->assertSee('--customer-desktop-gutter: 24px;', false)
            ->assertSee('body.app-shell--customer .content-wrapper', false)
            ->assertSee('max-width: none;', false);
    }
}
