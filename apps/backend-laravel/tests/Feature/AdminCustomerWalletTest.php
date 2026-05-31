<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_view_customer_account_context(): void
    {
        $admin = $this->userWithRole('super_admin');
        $customer = $this->customer('buyer@example.test', 'Buyer Account');
        $other = $this->customer('other@example.test', 'Other Account');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 250000]);
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'user_id' => $customer->id,
            'direction' => 'credit',
            'amount' => 250000,
            'currency' => 'VND',
            'balance_after' => 250000,
            'source_type' => 'seed',
            'idempotency_key' => 'seed-customer-wallet-view',
            'description' => 'Seed account credit',
            'meta' => [],
        ]);
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-CUSTOMER-VIEW']);
        $order = Order::factory()->for($customer)->create(['order_number' => 'ORD-CUSTOMER-VIEW']);
        Service::factory()->for($customer)->create(['product_name' => 'Managed Proxy', 'status' => 'active']);
        Service::factory()->for($customer)->create(['status' => 'suspended']);
        Service::factory()->for($customer)->create(['status' => 'cancelled']);

        $this->actingAs($admin)
            ->get('/admin/customers?search=buyer@example.test')
            ->assertOk()
            ->assertSee('Customers')
            ->assertDontSee('Customer Base')
            ->assertSee('Buyer Account')
            ->assertSee('buyer@example.test')
            ->assertSee('250,000 VND')
            ->assertSee('1 active')
            ->assertSee('1 suspended')
            ->assertSee('1 cancelled')
            ->assertDontSee($other->email);

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Buyer Account')
            ->assertSee('250,000 VND')
            ->assertSee('data-customer-activity-tabs', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('id="customer-details-tab"', false)
            ->assertSee('aria-controls="customer-details-panel"', false)
            ->assertSee('id="customer-details-panel"', false)
            ->assertSee('id="customer-ledger-tab"', false)
            ->assertSee('aria-controls="customer-ledger-panel"', false)
            ->assertSee('id="customer-invoices-tab"', false)
            ->assertSee('aria-controls="customer-invoices-panel"', false)
            ->assertSee('id="customer-orders-tab"', false)
            ->assertSee('aria-controls="customer-orders-panel"', false)
            ->assertSee('id="customer-services-tab"', false)
            ->assertSee('aria-controls="customer-services-panel"', false)
            ->assertDontSee('Account details, ledger, invoices, orders, and services are separated into tabs for faster review.')
            ->assertDontSee('Profile, wallet, and admin controls')
            ->assertDontSee('Current assignment and reseller routing.')
            ->assertDontSee('Available balances by currency.')
            ->assertDontSee('Credit or debit the customer wallet with an audited ledger entry.')
            ->assertSee('Seed account credit')
            ->assertSee($invoice->invoice_number)
            ->assertSee($order->order_number)
            ->assertSee('Managed Proxy');
    }

    public function test_finance_can_credit_and_debit_wallet_with_audited_ledger_entries(): void
    {
        $finance = $this->userWithRole('finance', 'finance@example.test');
        $customer = $this->customer('wallet-user@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 100000]);

        $this->actingAs($finance)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'credit',
            'amount' => 50000,
            'currency' => 'VND',
            'reason' => 'Manual goodwill credit',
            'reference' => 'ADJ-001',
        ])->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertSame(150000, $wallet->fresh()->balance_amount);
        $credit = LedgerEntry::firstOrFail();
        $this->assertSame('credit', $credit->direction);
        $this->assertSame('admin_wallet_adjustment', $credit->source_type);
        $this->assertSame('admin-wallet-adjustment:'.$customer->id.':VND:ADJ-001', $credit->idempotency_key);
        $this->assertSame('Manual goodwill credit', $credit->description);
        $this->assertSame($finance->id, $credit->meta['actor_id']);
        $this->assertSame('finance@example.test', $credit->meta['actor_email']);
        $this->assertSame('ADJ-001', $credit->meta['reference']);

        $this->actingAs($finance)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'credit',
            'amount' => 50000,
            'currency' => 'VND',
            'reason' => 'Manual goodwill credit',
            'reference' => 'ADJ-001',
        ])->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertSame(150000, $wallet->fresh()->balance_amount);
        $this->assertSame(1, LedgerEntry::count());

        $this->actingAs($finance)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'debit',
            'amount' => 30000,
            'currency' => 'VND',
            'reason' => 'Manual correction debit',
            'reference' => 'ADJ-002',
        ])->assertRedirect("/admin/customers/{$customer->id}");

        $this->assertSame(120000, $wallet->fresh()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 30000,
            'balance_after' => 120000,
            'idempotency_key' => 'admin-wallet-adjustment:'.$customer->id.':VND:ADJ-002',
        ]);
    }

    public function test_admin_customer_activity_tabs_are_paginated_by_default(): void
    {
        $admin = $this->userWithRole('super_admin');
        $customer = $this->customer('activity-pages@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 500000]);

        foreach (range(1, 12) as $index) {
            $createdAt = now()->subMinutes(12 - $index);
            $entry = LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'user_id' => $customer->id,
                'direction' => 'credit',
                'amount' => 1000 + $index,
                'currency' => 'VND',
                'balance_after' => 1000 + $index,
                'source_type' => 'seed',
                'idempotency_key' => 'activity-page-ledger-'.$index,
                'description' => sprintf('Ledger item %02d', $index),
                'meta' => [],
            ]);
            $entry->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            Invoice::factory()->for($customer)->create([
                'invoice_number' => sprintf('INV-PAGE-%02d', $index),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            Order::factory()->for($customer)->create([
                'order_number' => sprintf('ORD-PAGE-%02d', $index),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            Service::factory()->for($customer)->create([
                'product_name' => sprintf('Service Page %02d', $index),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('data-active-customer-tab="details"', false)
            ->assertSee('data-activity-pagination="ledger"', false)
            ->assertSee('data-activity-pagination="invoices"', false)
            ->assertSee('data-activity-pagination="orders"', false)
            ->assertSee('data-activity-pagination="services"', false)
            ->assertSee('ledger_page=2', false)
            ->assertSee('invoices_page=2', false)
            ->assertSee('orders_page=2', false)
            ->assertSee('services_page=2', false)
            ->assertSee('Ledger item 12')
            ->assertDontSee('Ledger item 02')
            ->assertSee('INV-PAGE-12')
            ->assertDontSee('INV-PAGE-02')
            ->assertSee('ORD-PAGE-12')
            ->assertDontSee('ORD-PAGE-02')
            ->assertSee('Service Page 12')
            ->assertDontSee('Service Page 02');

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}?activity_tab=ledger&ledger_page=2")
            ->assertOk()
            ->assertSee('data-active-customer-tab="ledger"', false)
            ->assertSee('Ledger item 02')
            ->assertSee('Ledger item 01')
            ->assertDontSee('Ledger item 12');
    }

    public function test_admin_wallet_debit_cannot_overdraw_balance(): void
    {
        $finance = $this->userWithRole('finance');
        $customer = $this->customer('overdraw@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 10000]);

        $this->actingAs($finance)->from("/admin/customers/{$customer->id}")
            ->post("/admin/customers/{$customer->id}/wallet-adjustments", [
                'direction' => 'debit',
                'amount' => 20000,
                'currency' => 'VND',
                'reason' => 'Invalid debit',
                'reference' => 'ADJ-OVERDRAW',
            ])
            ->assertRedirect("/admin/customers/{$customer->id}")
            ->assertSessionHasErrors('amount');

        $this->assertSame(10000, $wallet->fresh()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_support_can_view_customers_but_cannot_adjust_wallets(): void
    {
        $support = $this->userWithRole('support');
        $customer = $this->customer('support-view@example.test');
        Wallet::factory()->for($customer)->create(['balance_amount' => 75000]);

        $this->actingAs($support)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee('support-view@example.test');

        $this->actingAs($support)
            ->get("/admin/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('75,000 VND')
            ->assertDontSee('Manual Adjustment');

        $this->actingAs($support)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'credit',
            'amount' => 10000,
            'currency' => 'VND',
            'reason' => 'Support should not adjust',
            'reference' => 'SUPPORT-DENIED',
        ])->assertForbidden();
    }

    public function test_customer_cannot_access_admin_customer_pages(): void
    {
        $customer = $this->customer('customer-denied@example.test');

        $this->actingAs($customer)->get('/admin/customers')->assertForbidden();
        $this->actingAs($customer)->get("/admin/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($customer)->post("/admin/customers/{$customer->id}/wallet-adjustments", [
            'direction' => 'credit',
            'amount' => 10000,
            'currency' => 'VND',
            'reason' => 'Denied',
        ])->assertForbidden();
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email ?? "{$role}@example.test"]);
        $user->assignRole($role);

        return $user;
    }

    private function customer(string $email, string $name = 'Customer Account'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email, 'name' => $name]);
        $user->assignRole('customer');

        return $user;
    }
}
