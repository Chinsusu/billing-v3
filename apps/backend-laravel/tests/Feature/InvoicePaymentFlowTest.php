<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_own_invoice(): void
    {
        $customer = $this->customerUser();
        $invoice = Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-20260524-0001',
            'status' => 'open',
            'total_amount' => 99000,
        ]);

        $this->actingAs($customer)
            ->get("/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('INV-20260524-0001')
            ->assertSee('99,000 VND');
    }

    public function test_customer_can_pay_open_invoice_from_wallet(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 200000]);
        $invoice = Invoice::factory()->for($customer)->create([
            'status' => 'open',
            'total_amount' => 99000,
            'currency' => 'VND',
        ]);

        $this->actingAs($customer)
            ->post("/invoices/{$invoice->id}/pay")
            ->assertRedirect("/invoices/{$invoice->id}");

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(101000, Wallet::firstOrFail()->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'debit',
            'amount' => 99000,
            'balance_after' => 101000,
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'idempotency_key' => "invoice-payment:{$invoice->id}",
        ]);
    }

    public function test_customer_cannot_pay_invoice_without_enough_wallet_balance(): void
    {
        $customer = $this->customerUser();
        Wallet::factory()->for($customer)->create(['balance_amount' => 1000]);
        $invoice = Invoice::factory()->for($customer)->create([
            'status' => 'open',
            'total_amount' => 99000,
            'currency' => 'VND',
        ]);

        $this->actingAs($customer)
            ->from("/invoices/{$invoice->id}")
            ->post("/invoices/{$invoice->id}/pay")
            ->assertRedirect("/invoices/{$invoice->id}")
            ->assertSessionHasErrors('wallet');

        $this->assertSame('open', $invoice->refresh()->status);
        $this->assertSame(1000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_customer_cannot_view_or_pay_another_users_invoice(): void
    {
        $owner = $this->customerUser();
        $other = User::factory()->create();
        $other->assignRole('customer');
        $invoice = Invoice::factory()->for($owner)->create(['status' => 'open']);

        $this->actingAs($other)->get("/invoices/{$invoice->id}")->assertNotFound();
        $this->actingAs($other)->post("/invoices/{$invoice->id}/pay")->assertNotFound();
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
