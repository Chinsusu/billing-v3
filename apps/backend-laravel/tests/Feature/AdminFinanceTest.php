<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\PaymentEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_customer_cannot_access_admin_finance_pages(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/invoices')->assertForbidden();
        $this->actingAs($customer)->get('/admin/payment-events')->assertForbidden();
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
