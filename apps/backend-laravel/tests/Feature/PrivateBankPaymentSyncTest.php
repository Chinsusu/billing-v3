<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrivateBankPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_bank_sync_accepts_matching_transaction_and_credits_wallet_once(): void
    {
        $integration = $this->bankIntegration();
        $customer = $this->customerUser();
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        $intent = PaymentIntent::create([
            'user_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'type' => 'wallet_topup',
            'target_type' => 'wallet',
            'reference' => 'TOPUP-PRIVATE-1',
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'pending',
            'qr_payload' => 'BANKQR|BILLINGV3|TOPUP-PRIVATE-1|150000|VND',
            'meta' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        Http::fake([
            'https://bank.example.test/api/transactions' => Http::response([
                'transactions' => [[
                    'transaction_id' => 'PRIVATE-TXN-0001',
                    'reference' => 'TOPUP-PRIVATE-1',
                    'amount' => 150000,
                    'currency' => 'VND',
                    'paid_at' => '2026-05-24T09:00:00+07:00',
                ]],
            ]),
        ]);

        $this->artisan('bank:sync-payments')
            ->expectsOutput('private_bank: accepted=1 rejected=0 unmatched=0 duplicate=0')
            ->assertExitCode(0);

        $this->assertSame(150000, $wallet->refresh()->balance_amount);
        $this->assertSame('succeeded', $intent->refresh()->status);
        $this->assertDatabaseHas('payment_events', [
            'provider' => 'private_bank',
            'provider_transaction_id' => 'PRIVATE-TXN-0001',
            'reference' => 'TOPUP-PRIVATE-1',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $customer->id,
            'direction' => 'credit',
            'amount' => 150000,
            'balance_after' => 150000,
            'idempotency_key' => 'private_bank:PRIVATE-TXN-0001',
        ]);
        $this->assertSame('synced', $integration->refresh()->last_sync_status);

        $this->artisan('bank:sync-payments')
            ->expectsOutput('private_bank: accepted=0 rejected=0 unmatched=0 duplicate=1')
            ->assertExitCode(0);

        $this->assertSame(150000, $wallet->refresh()->balance_amount);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_private_bank_sync_records_unmatched_and_rejected_transactions(): void
    {
        $this->bankIntegration();
        $customer = $this->customerUser();
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        PaymentIntent::create([
            'user_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'type' => 'wallet_topup',
            'target_type' => 'wallet',
            'reference' => 'TOPUP-PRIVATE-2',
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'pending',
            'qr_payload' => 'BANKQR|BILLINGV3|TOPUP-PRIVATE-2|150000|VND',
            'meta' => [],
            'expires_at' => now()->addMinutes(30),
        ]);

        Http::fake([
            'https://bank.example.test/api/transactions' => Http::response([
                'transactions' => [
                    [
                        'transaction_id' => 'PRIVATE-TXN-UNMATCHED',
                        'reference' => 'TOPUP-MISSING',
                        'amount' => 90000,
                        'currency' => 'VND',
                        'paid_at' => '2026-05-24T09:00:00+07:00',
                    ],
                    [
                        'transaction_id' => 'PRIVATE-TXN-REJECTED',
                        'reference' => 'TOPUP-PRIVATE-2',
                        'amount' => 90000,
                        'currency' => 'VND',
                        'paid_at' => '2026-05-24T09:00:00+07:00',
                    ],
                ],
            ]),
        ]);

        $this->artisan('bank:sync-payments')
            ->expectsOutput('private_bank: accepted=0 rejected=1 unmatched=1 duplicate=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('payment_events', [
            'provider' => 'private_bank',
            'provider_transaction_id' => 'PRIVATE-TXN-UNMATCHED',
            'reference' => 'TOPUP-MISSING',
            'status' => 'unmatched',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'provider' => 'private_bank',
            'provider_transaction_id' => 'PRIVATE-TXN-REJECTED',
            'reference' => 'TOPUP-PRIVATE-2',
            'status' => 'rejected',
        ]);
        $this->assertSame(0, $wallet->refresh()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    private function bankIntegration(): BankIntegration
    {
        $admin = $this->adminUser();

        return BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'account_number' => '123456789',
            'enabled' => true,
            'api_key' => 'private-api-secret-1234',
            'api_key_last_four' => '1234',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }
}
