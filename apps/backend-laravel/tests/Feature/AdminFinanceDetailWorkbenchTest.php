<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceDetailWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_invoice_detail_with_events_and_ledger_context(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('invoice-detail@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        $invoice = Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-S17-DETAIL',
            'status' => 'paid',
            'total_amount' => 99000,
            'description' => 'S17 invoice detail',
            'lines' => [
                ['description' => 'Proxy Plan', 'amount' => 99000],
            ],
            'paid_at' => now(),
        ]);
        PaymentEvent::create([
            'wallet_id' => $wallet->id,
            'invoice_id' => $invoice->id,
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-S17-INVOICE',
            'reference' => 'INV-S17-DETAIL',
            'amount' => 99000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => ['reference' => 'INV-S17-DETAIL'],
            'processed_at' => now(),
        ]);
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 99000,
            'currency' => 'VND',
            'balance_after' => 151000,
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'idempotency_key' => 'invoice:S17',
            'description' => 'Invoice paid.',
            'meta' => ['invoice_number' => $invoice->invoice_number],
        ]);

        $this->actingAs($admin)
            ->get("/admin/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('INV-S17-DETAIL')
            ->assertSee('invoice-detail@example.test')
            ->assertSee('Proxy Plan')
            ->assertSee('BANK-S17-INVOICE')
            ->assertSee('Invoice paid.')
            ->assertSee("href=\"/admin/customers/{$customer->id}\"", false);
    }

    public function test_admin_can_view_order_detail_with_items_services_jobs_and_ledger_context(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('order-detail@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 101000]);
        $product = Product::factory()->create([
            'code' => 's17-vps',
            'name' => 'S17 VPS',
            'type' => 'vps',
            'price_amount' => 199000,
        ]);
        $order = Order::factory()->for($customer)->create([
            'order_number' => 'ORD-S17-DETAIL',
            'total_amount' => 199000,
            'subtotal_amount' => 199000,
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => 's17-vps',
            'product_name' => 'S17 VPS',
            'product_type' => 'vps',
            'unit_amount' => 199000,
            'subtotal_amount' => 199000,
            'config_snapshot' => ['region' => 'sgp'],
        ]);
        $service = Service::factory()->for($customer)->for($order)->for($item, 'orderItem')->for($product)->create([
            'product_code' => 's17-vps',
            'product_name' => 'S17 VPS',
            'product_type' => 'vps',
            'status' => 'active',
        ]);
        ProvisioningJob::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 2,
            'idempotency_key' => 's17-provisioning-job',
            'payload' => ['product' => ['code' => 's17-vps']],
            'last_error' => 'Provider rejected order.',
        ]);
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 199000,
            'currency' => 'VND',
            'balance_after' => 101000,
            'source_type' => 'order',
            'source_id' => $order->id,
            'idempotency_key' => 'order:S17',
            'description' => 'Order paid.',
            'meta' => ['order_number' => $order->order_number],
        ]);

        $this->actingAs($admin)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('ORD-S17-DETAIL')
            ->assertSee('order-detail@example.test')
            ->assertSee('S17 VPS')
            ->assertSee('active')
            ->assertSee('Provider rejected order.')
            ->assertSee('Order paid.')
            ->assertSee("href=\"/admin/services/{$service->id}\"", false);
    }

    public function test_admin_can_view_payment_event_detail_with_linked_context_and_payload(): void
    {
        $admin = $this->adminUser();
        $customer = $this->customerUser('payment-event-detail@example.test');
        $wallet = Wallet::factory()->for($customer)->create();
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-S17-EVENT']);
        $intent = PaymentIntent::factory()->for($customer)->for($wallet)->for($invoice)->create([
            'reference' => 'TOPUP-S17-EVENT',
            'amount' => 150000,
        ]);
        $event = PaymentEvent::create([
            'payment_intent_id' => $intent->id,
            'wallet_id' => $wallet->id,
            'invoice_id' => $invoice->id,
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-S17-EVENT',
            'reference' => 'TOPUP-S17-EVENT',
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => ['bank_account' => '123456', 'reference' => 'TOPUP-S17-EVENT'],
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/admin/payment-events/{$event->id}")
            ->assertOk()
            ->assertSee('BANK-S17-EVENT')
            ->assertSee('TOPUP-S17-EVENT')
            ->assertSee('payment-event-detail@example.test')
            ->assertSee('INV-S17-EVENT')
            ->assertSee('123456')
            ->assertSee("href=\"/admin/invoices/{$invoice->id}\"", false)
            ->assertSee("href=\"/admin/customers/{$customer->id}\"", false);
    }

    public function test_admin_lists_link_to_details_and_filter_rows(): void
    {
        $admin = $this->adminUser();
        $visible = $this->customerUser('visible-finance@example.test');
        $hidden = $this->customerUser('hidden-finance@example.test');
        $invoice = Invoice::factory()->for($visible)->create(['invoice_number' => 'INV-S17-VISIBLE', 'status' => 'open']);
        Invoice::factory()->for($hidden)->create(['invoice_number' => 'INV-S17-HIDDEN', 'status' => 'paid']);
        $order = Order::factory()->for($visible)->create(['order_number' => 'ORD-S17-VISIBLE', 'status' => 'paid']);
        Order::factory()->for($hidden)->create(['order_number' => 'ORD-S17-HIDDEN', 'status' => 'pending']);
        $event = PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-S17-VISIBLE',
            'reference' => 'REF-S17-VISIBLE',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => ['reference' => 'REF-S17-VISIBLE'],
            'processed_at' => now(),
        ]);
        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-S17-HIDDEN',
            'reference' => 'REF-S17-HIDDEN',
            'amount' => 200000,
            'currency' => 'VND',
            'status' => 'rejected',
            'signature_status' => 'valid',
            'payload' => ['reference' => 'REF-S17-HIDDEN'],
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/invoices?status=open&customer=visible-finance@example.test')
            ->assertOk()
            ->assertSee('INV-S17-VISIBLE')
            ->assertSee("href=\"/admin/invoices/{$invoice->id}\"", false)
            ->assertDontSee('INV-S17-HIDDEN');

        $this->actingAs($admin)
            ->get('/admin/orders?status=paid&customer=visible-finance@example.test')
            ->assertOk()
            ->assertSee('ORD-S17-VISIBLE')
            ->assertSee("href=\"/admin/orders/{$order->id}\"", false)
            ->assertDontSee('ORD-S17-HIDDEN');

        $this->actingAs($admin)
            ->get('/admin/payment-events?status=accepted&reference=REF-S17-VISIBLE')
            ->assertOk()
            ->assertSee('BANK-S17-VISIBLE')
            ->assertSee("href=\"/admin/payment-events/{$event->id}\"", false)
            ->assertDontSee('BANK-S17-HIDDEN');
    }

    public function test_customer_cannot_access_admin_finance_detail_pages(): void
    {
        $customer = $this->customerUser('blocked-finance@example.test');
        $invoice = Invoice::factory()->for($customer)->create();
        $order = Order::factory()->for($customer)->create();
        $event = PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'BANK-S17-BLOCKED',
            'reference' => 'REF-S17-BLOCKED',
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => [],
            'processed_at' => now(),
        ]);

        $this->actingAs($customer)->get("/admin/invoices/{$invoice->id}")->assertForbidden();
        $this->actingAs($customer)->get("/admin/orders/{$order->id}")->assertForbidden();
        $this->actingAs($customer)->get("/admin/payment-events/{$event->id}")->assertForbidden();
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
