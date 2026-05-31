<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_admin_can_edit_invoice_status(): void
    {
        $admin = $this->adminUser();
        $this->travelTo(Carbon::parse('2026-05-27 10:43:00'));
        $customer = User::factory()->create(['email' => 'status-buyer@example.test']);
        $invoice = Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-STATUS-EDIT',
            'status' => 'open',
            'paid_at' => null,
        ]);

        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->id}/edit")
            ->assertOk()
            ->assertSee('Edit Invoice')
            ->assertSee('INV-STATUS-EDIT')
            ->assertSee('Status')
            ->assertSee('value="paid"', false)
            ->assertSee('name="processed_at"', false)
            ->assertSee('name="paid_at"', false)
            ->assertSee('value="2026-05-27T10:43"', false);

        $this->actingAs($admin)
            ->from("/admin/invoices/{$invoice->id}/edit")
            ->put("/admin/invoices/{$invoice->id}", [
                'status' => 'paid',
            ])
            ->assertRedirect("/admin/invoices/{$invoice->id}");

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('2026-05-27 10:43:00', $invoice->paid_at?->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'invoice_status_updated',
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
        ]);
    }

    public function test_admin_paid_invoice_status_records_manual_transaction_and_ledger(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create(['email' => 'manual-topup@example.test']);
        $invoice = Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-MANUAL-TOPUP',
            'status' => 'open',
            'total_amount' => 250000,
            'currency' => 'VND',
        ]);

        $this->actingAs($admin)
            ->from("/admin/invoices/{$invoice->id}/edit")
            ->put("/admin/invoices/{$invoice->id}", [
                'status' => 'paid',
                'provider_transaction_id' => 'MANUAL-TXN-001',
                'payment_reference' => 'MANUAL-REF-001',
                'processed_at' => '2026-05-27 09:30:00',
                'paid_at' => '2026-05-27 10:45:00',
            ])
            ->assertRedirect("/admin/invoices/{$invoice->id}");

        $event = PaymentEvent::firstOrFail();
        $wallet = Wallet::where('user_id', $customer->id)->firstOrFail();
        $ledger = LedgerEntry::firstOrFail();

        $this->assertSame('manual_admin', $event->provider);
        $this->assertSame('MANUAL-TXN-001', $event->provider_transaction_id);
        $this->assertSame('MANUAL-REF-001', $event->reference);
        $this->assertSame('accepted', $event->status);
        $this->assertSame($invoice->id, $event->invoice_id);
        $this->assertSame($wallet->id, $event->wallet_id);
        $this->assertSame('2026-05-27 09:30:00', $event->processed_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-27 10:45:00', $invoice->refresh()->paid_at?->format('Y-m-d H:i:s'));
        $this->assertSame(250000, $wallet->balance_amount);
        $this->assertSame('credit', $ledger->direction);
        $this->assertSame('manual_invoice_payment', $ledger->source_type);
        $this->assertSame($invoice->id, $ledger->source_id);
        $this->assertSame("manual-invoice-payment:{$invoice->id}", $ledger->idempotency_key);
        $this->assertSame($event->id, $ledger->meta['payment_event_id']);

        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('MANUAL-TXN-001')
            ->assertSee('MANUAL-REF-001')
            ->assertSee('Manual invoice top-up INV-MANUAL-TOPUP')
            ->assertSee('manual_invoice_payment');
    }

    public function test_admin_can_edit_manual_payment_event_transaction_fields(): void
    {
        $admin = $this->adminUser();
        $customer = User::factory()->create(['email' => 'manual-event-edit@example.test']);
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-MANUAL-EVENT']);

        $this->actingAs($admin)->put("/admin/invoices/{$invoice->id}", [
            'status' => 'paid',
            'provider_transaction_id' => 'MANUAL-TXN-OLD',
            'payment_reference' => 'MANUAL-REF-OLD',
        ]);

        $event = PaymentEvent::firstOrFail();

        $this->actingAs($admin)
            ->get("/admin/payment-events/{$event->id}/edit")
            ->assertOk()
            ->assertSee('Edit Transaction')
            ->assertSee('MANUAL-TXN-OLD')
            ->assertSee('MANUAL-REF-OLD');

        $this->actingAs($admin)
            ->from("/admin/payment-events/{$event->id}/edit")
            ->put("/admin/payment-events/{$event->id}", [
                'provider_transaction_id' => 'MANUAL-TXN-NEW',
                'reference' => 'MANUAL-REF-NEW',
                'processed_at' => '2026-05-27 11:15:00',
            ])
            ->assertRedirect("/admin/payment-events/{$event->id}");

        $event->refresh();
        $this->assertSame('MANUAL-TXN-NEW', $event->provider_transaction_id);
        $this->assertSame('MANUAL-REF-NEW', $event->reference);
        $this->assertSame(99000, Wallet::where('user_id', $customer->id)->firstOrFail()->balance_amount);
        $this->assertSame(1, LedgerEntry::count());

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'payment_event_updated',
            'auditable_type' => PaymentEvent::class,
            'auditable_id' => $event->id,
        ]);
    }

    public function test_invoice_update_permission_controls_edit_entrypoint(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('admin.access'));
        $viewer->givePermissionTo(Permission::findOrCreate('invoices.view'));
        $invoice = Invoice::factory()->for(User::factory()->create())->create();

        $this->actingAs($viewer)
            ->get('/admin/invoices')
            ->assertOk()
            ->assertDontSee('Edit</span>', false);

        $this->actingAs($viewer)->get("/admin/invoices/{$invoice->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/admin/invoices/{$invoice->id}", ['status' => 'void'])->assertForbidden();
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
        $this->actingAs($customer)->get('/admin/invoices/'.Invoice::factory()->for($customer)->create()->id.'/edit')->assertForbidden();
        $this->actingAs($customer)->put('/admin/invoices/'.Invoice::factory()->for($customer)->create()->id, ['status' => 'void'])->assertForbidden();
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
