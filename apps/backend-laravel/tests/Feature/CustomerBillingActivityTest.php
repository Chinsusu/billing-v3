<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBillingActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_only_their_orders(): void
    {
        $customer = $this->customer('orders@example.test');
        $other = $this->customer('other-orders@example.test');
        $order = Order::factory()->for($customer)->create(['order_number' => 'ORD-MINE-001']);
        Order::factory()->for($other)->create(['order_number' => 'ORD-OTHER-001']);

        $this->actingAs($customer)
            ->get('/orders')
            ->assertOk()
            ->assertSee('Orders')
            ->assertSee($order->order_number)
            ->assertSee('href="/orders/'.$order->id.'"', false)
            ->assertDontSee('ORD-OTHER-001');
    }

    public function test_customer_can_list_only_their_invoices(): void
    {
        $customer = $this->customer('invoices@example.test');
        $other = $this->customer('other-invoices@example.test');
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-MINE-001', 'status' => 'open']);
        Invoice::factory()->for($other)->create(['invoice_number' => 'INV-OTHER-001']);

        $this->actingAs($customer)
            ->get('/invoices')
            ->assertOk()
            ->assertSee('Invoices')
            ->assertSee($invoice->invoice_number)
            ->assertSee('open')
            ->assertSee('href="/invoices/'.$invoice->id.'"', false)
            ->assertDontSee('INV-OTHER-001');
    }

    public function test_customer_can_list_only_their_wallet_top_ups(): void
    {
        $customer = $this->customer('topups@example.test');
        $other = $this->customer('other-topups@example.test');
        $wallet = Wallet::factory()->for($customer)->create();
        $otherWallet = Wallet::factory()->for($other)->create();
        $intent = PaymentIntent::factory()->for($customer)->for($wallet)->create(['reference' => 'TOPUP-MINE-001']);
        PaymentIntent::factory()->for($other)->for($otherWallet)->create(['reference' => 'TOPUP-OTHER-001']);

        $this->actingAs($customer)
            ->get('/wallet/top-ups')
            ->assertOk()
            ->assertSee('Wallet Top-ups')
            ->assertSee($intent->reference)
            ->assertSee('href="/wallet/top-ups/'.$intent->id.'"', false)
            ->assertDontSee('TOPUP-OTHER-001');
    }

    public function test_customer_dashboard_shows_activity_snapshot_and_links(): void
    {
        $customer = $this->customer('dashboard@example.test');
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 450000]);
        $invoice = Invoice::factory()->for($customer)->create(['invoice_number' => 'INV-DASH-001', 'status' => 'open']);
        $order = Order::factory()->for($customer)->create(['order_number' => 'ORD-DASH-001']);
        $service = Service::factory()->for($customer)->create(['product_name' => 'Dashboard Proxy', 'status' => 'active']);
        $intent = PaymentIntent::factory()->for($customer)->for($wallet)->create(['reference' => 'TOPUP-DASH-001']);

        $this->actingAs($customer)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('450,000 VND')
            ->assertSee('Open invoices')
            ->assertSee('1')
            ->assertSee($invoice->invoice_number)
            ->assertSee($order->order_number)
            ->assertSee($service->product_name)
            ->assertSee($intent->reference)
            ->assertSee('href="/orders"', false)
            ->assertSee('href="/invoices"', false)
            ->assertSee('href="/wallet/top-ups"', false);
    }

    public function test_guests_are_redirected_from_customer_history_pages(): void
    {
        $this->get('/orders')->assertRedirect('/login');
        $this->get('/invoices')->assertRedirect('/login');
        $this->get('/wallet/top-ups')->assertRedirect('/login');
    }

    private function customer(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('customer');

        return $user;
    }
}
