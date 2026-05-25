<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingExportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_invoice_ledger_and_payment_csv_reports(): void
    {
        $admin = $this->superAdmin('s39-admin@example.test');
        $customer = $this->customer('s39-customer@example.test');
        Invoice::factory()->for($customer)->create([
            'invoice_number' => 'INV-S39-001',
            'status' => 'paid',
            'total_amount' => 120000,
            'currency' => 'VND',
        ]);
        LedgerEntry::create([
            'wallet_id' => null,
            'user_id' => $customer->id,
            'direction' => 'credit',
            'amount' => 120000,
            'currency' => 'VND',
            'balance_after' => 120000,
            'source_type' => 'test',
            'source_id' => 'S39',
            'idempotency_key' => 's39-ledger',
            'description' => 'S39 ledger export',
            'meta' => [],
        ]);
        PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'S39-TXN',
            'reference' => 'S39-REF',
            'amount' => 120000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => [],
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports/billing')
            ->assertOk()
            ->assertSee('Billing Reports');

        $this->actingAs($admin)
            ->get('/admin/reports/billing/invoices.csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('INV-S39-001');

        $this->actingAs($admin)
            ->get('/admin/reports/billing/ledger.csv')
            ->assertOk()
            ->assertSee('S39 ledger export');

        $this->actingAs($admin)
            ->get('/admin/reports/billing/payment-events.csv')
            ->assertOk()
            ->assertSee('S39-TXN');

        $this->assertSame(3, AdminAuditLog::where('action', 'billing_report_exported')->count());
    }

    private function superAdmin(string $email): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return $this->userWithRole('super_admin', $email);
    }

    private function customer(string $email): User
    {
        return $this->userWithRole('customer', $email);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
