<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_invoice_button_and_page(): void
    {
        $admin = $this->adminUser();
        User::factory()->create(['email' => 'invoice-create-customer@example.test'])->assignRole('customer');

        $this->actingAs($admin)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee('Create Invoice')
            ->assertSee('href="/admin/invoices/create"', false);

        $this->actingAs($admin)
            ->get('/admin/invoices/create')
            ->assertOk()
            ->assertSee('Create Invoice')
            ->assertSee('invoice-create-customer@example.test')
            ->assertSee('Customer email')
            ->assertSee('Search customers')
            ->assertSee('data-realtime-search', false)
            ->assertSee('data-realtime-search-input', false)
            ->assertSee('data-realtime-search-option', false)
            ->assertDontSee('data-customer-picker', false)
            ->assertSee('Total amount')
            ->assertSee('placeholder="Nạp Tiền"', false)
            ->assertSee('Description');
    }

    public function test_admin_can_create_invoice_for_customer(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create(['email' => 'buyer@example.test']);
        $customer->assignRole('customer');

        $this->actingAs($admin)->post('/admin/invoices', [
            'user_email' => 'buyer@example.test',
            'total_amount' => 199000,
            'currency' => 'VND',
            'description' => 'Basic VPS 30 Days',
        ])->assertRedirect('/admin/invoices');

        $invoice = Invoice::firstOrFail();
        $this->assertSame($customer->id, $invoice->user_id);
        $this->assertSame('open', $invoice->status);
        $this->assertSame(199000, $invoice->total_amount);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);

        $this->actingAs($admin)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('buyer@example.test');
    }

    public function test_admin_create_invoice_uses_default_description_when_blank(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create(['email' => 'topup-buyer@example.test']);
        $customer->assignRole('customer');

        $this->actingAs($admin)->post('/admin/invoices', [
            'user_email' => 'topup-buyer@example.test',
            'total_amount' => 500000,
            'currency' => 'VND',
        ])->assertRedirect('/admin/invoices');

        $invoice = Invoice::firstOrFail();
        $this->assertSame('Nạp Tiền', $invoice->description);
        $this->assertSame([['description' => 'Nạp Tiền', 'amount' => 500000]], $invoice->lines);
    }

    public function test_admin_can_view_payment_events(): void
    {
        $admin = $this->adminUser();
        PaymentEvent::create([
            'provider' => 'bank_sandbox',
            'provider_transaction_id' => 'BANK-TXN-VIEW',
            'reference' => 'TOPUP-VIEW',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => ['reference' => 'TOPUP-VIEW'],
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/payment-events')
            ->assertOk()
            ->assertSee('BANK-TXN-VIEW')
            ->assertSee('accepted')
            ->assertSee('TOPUP-VIEW');
    }

    public function test_admin_invoice_index_uses_compact_filters_and_toast_status(): void
    {
        $admin = $this->adminUser();
        User::factory()->create(['email' => 'invoice-filter-customer@example.test'])->assignRole('customer');

        $this->actingAs($admin)
            ->withSession(['status' => 'Invoice created.'])
            ->get('/admin/invoices')
            ->assertOk()
            ->assertSee('class="panel invoice-filter-panel"', false)
            ->assertSee('class="invoice-filter-form"', false)
            ->assertSee('Customer email')
            ->assertSee('Search customer email')
            ->assertSee('invoice-filter-customer@example.test')
            ->assertSee('data-realtime-search', false)
            ->assertSee('data-realtime-search-input', false)
            ->assertSee('data-realtime-search-option', false)
            ->assertSee('data-flash-toast', false)
            ->assertSee('data-toast-dismiss-ms="1200"', false);
    }

    public function test_customer_cannot_access_admin_finance_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/invoices')->assertForbidden();
        $this->actingAs($customer)->get('/admin/invoices/create')->assertForbidden();
        $this->actingAs($customer)->post('/admin/invoices', [])->assertForbidden();
        $this->actingAs($customer)->get('/admin/payment-events')->assertForbidden();
    }

    public function test_invoice_create_permission_controls_create_entrypoint(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('admin.access'));
        $viewer->givePermissionTo(Permission::findOrCreate('invoices.view'));

        $this->actingAs($viewer)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertDontSee('Create Invoice');

        $this->actingAs($viewer)->get('/admin/invoices/create')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/invoices', [])->assertForbidden();
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
