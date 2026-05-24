<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentIntentExpiryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expiry_command_marks_only_stale_pending_payment_intents(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-24 10:00:00'));

        $customer = $this->customerUser();
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        $stale = $this->paymentIntent($customer, $wallet, [
            'reference' => 'TOPUP-EXPIRE-STALE',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
        $future = $this->paymentIntent($customer, $wallet, [
            'reference' => 'TOPUP-EXPIRE-FUTURE',
            'status' => 'pending',
            'expires_at' => now()->addMinute(),
        ]);
        $succeeded = $this->paymentIntent($customer, $wallet, [
            'reference' => 'TOPUP-EXPIRE-SUCCEEDED',
            'status' => 'succeeded',
            'expires_at' => now()->subMinute(),
            'paid_at' => now()->subSecond(),
        ]);

        $this->artisan('payment-intents:expire')
            ->expectsOutput('Expired payment intents: 1')
            ->assertExitCode(0);

        $this->assertSame('expired', $stale->refresh()->status);
        $this->assertSame('pending', $future->refresh()->status);
        $this->assertSame('succeeded', $succeeded->refresh()->status);

        $this->artisan('payment-intents:expire')
            ->expectsOutput('Expired payment intents: 0')
            ->assertExitCode(0);
    }

    public function test_signed_sandbox_webhook_records_expired_intent_without_wallet_credit(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'test-bank-secret']);

        $customer = $this->customerUser();
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        $intent = $this->paymentIntent($customer, $wallet, [
            'reference' => 'TOPUP-EXPIRED-SANDBOX',
            'status' => 'expired',
            'amount' => 125000,
            'expires_at' => now()->subMinute(),
        ]);

        $this->signedBankWebhook([
            'reference' => 'TOPUP-EXPIRED-SANDBOX',
            'amount' => 125000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-TXN-EXPIRED',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'expired');

        $this->assertDatabaseHas('payment_events', [
            'payment_intent_id' => $intent->id,
            'wallet_id' => $wallet->id,
            'provider' => 'bank_sandbox',
            'provider_transaction_id' => 'BANK-TXN-EXPIRED',
            'reference' => 'TOPUP-EXPIRED-SANDBOX',
            'status' => 'expired',
        ]);
        $this->assertSame('expired', $intent->refresh()->status);
        $this->assertSame(0, $wallet->refresh()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_private_bank_sync_records_expired_intent_without_wallet_credit(): void
    {
        $integration = $this->bankIntegration();
        $customer = $this->customerUser();
        $wallet = Wallet::factory()->for($customer)->create(['balance_amount' => 0]);
        $intent = $this->paymentIntent($customer, $wallet, [
            'reference' => 'TOPUP-EXPIRED-PRIVATE',
            'status' => 'expired',
            'amount' => 150000,
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            'https://bank.example.test/api/transactions' => Http::response([
                'transactions' => [[
                    'transaction_id' => 'PRIVATE-TXN-EXPIRED',
                    'reference' => 'TOPUP-EXPIRED-PRIVATE',
                    'amount' => 150000,
                    'currency' => 'VND',
                    'paid_at' => '2026-05-24T09:00:00+07:00',
                ]],
            ]),
        ]);

        $this->artisan('bank:sync-payments')
            ->expectsOutput('private_bank: accepted=0 rejected=0 unmatched=0 expired=1 duplicate=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('payment_events', [
            'payment_intent_id' => $intent->id,
            'wallet_id' => $wallet->id,
            'provider' => 'private_bank',
            'provider_transaction_id' => 'PRIVATE-TXN-EXPIRED',
            'reference' => 'TOPUP-EXPIRED-PRIVATE',
            'status' => 'expired',
        ]);
        $this->assertSame('expired', $intent->refresh()->status);
        $this->assertSame(0, $wallet->refresh()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
        $this->assertSame('synced', $integration->refresh()->last_sync_status);
    }

    public function test_finance_admin_can_reconcile_expired_payment_event_to_customer_wallet_once(): void
    {
        $admin = $this->financeUser();
        $customer = $this->customerUser();
        $event = PaymentEvent::create([
            'provider' => 'private_bank',
            'provider_transaction_id' => 'PRIVATE-TXN-RECONCILE',
            'reference' => 'BANK-UNMATCHED-RECONCILE',
            'amount' => 175000,
            'currency' => 'VND',
            'status' => 'expired',
            'signature_status' => 'valid',
            'payload' => ['raw' => 'bank row'],
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/admin/payment-events/{$event->id}/reconcile-wallet", [
                'user_email' => $customer->email,
            ])
            ->assertRedirect("/admin/payment-events/{$event->id}");

        $wallet = Wallet::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(175000, $wallet->balance_amount);
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $wallet->id,
            'user_id' => $customer->id,
            'direction' => 'credit',
            'amount' => 175000,
            'balance_after' => 175000,
            'source_type' => 'payment_event_reconciliation',
            'source_id' => $event->id,
            'idempotency_key' => "payment-event-reconcile:{$event->id}",
        ]);

        $event->refresh();
        $this->assertSame('reconciled', $event->status);
        $this->assertSame($wallet->id, $event->wallet_id);
        $this->assertSame($admin->id, $event->payload['reconciliation']['actor_id']);
        $this->assertSame($customer->id, $event->payload['reconciliation']['user_id']);
        $this->assertDatabaseHas('notification_events', [
            'user_id' => $customer->id,
            'type' => 'wallet_credited',
            'recipient_email' => $customer->email,
            'source_type' => 'payment_event',
            'source_id' => $event->id,
            'idempotency_key' => "wallet-credited:payment-event:{$event->id}",
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/admin/payment-events/{$event->id}/reconcile-wallet", [
                'user_email' => $customer->email,
            ])
            ->assertSessionHasErrors('payment_event');

        $this->assertSame(175000, $wallet->refresh()->balance_amount);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(1, DB::table('notification_events')->where('idempotency_key', "wallet-credited:payment-event:{$event->id}")->count());
    }

    public function test_reconciliation_requires_wallet_adjust_permission_and_rejects_accepted_events(): void
    {
        $customer = $this->customerUser();
        $admin = $this->financeUser();
        $event = PaymentEvent::create([
            'provider' => 'bank_sandbox',
            'provider_transaction_id' => 'BANK-TXN-ACCEPTED',
            'reference' => 'TOPUP-ACCEPTED',
            'amount' => 80000,
            'currency' => 'VND',
            'status' => 'accepted',
            'signature_status' => 'valid',
            'payload' => [],
            'processed_at' => now(),
        ]);

        $this->actingAs($customer)
            ->post("/admin/payment-events/{$event->id}/reconcile-wallet", [
                'user_email' => $customer->email,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post("/admin/payment-events/{$event->id}/reconcile-wallet", [
                'user_email' => $customer->email,
            ])
            ->assertSessionHasErrors('payment_event');

        $this->assertSame('accepted', $event->refresh()->status);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_payment_intent_expiry_is_registered_as_scheduled_task(): void
    {
        $registry = app(ScheduledTaskRegistry::class);

        $this->assertSame('payment-intents:expire', $registry->commandFor('payment_intents_expire'));
    }

    private function paymentIntent(User $user, Wallet $wallet, array $overrides = []): PaymentIntent
    {
        $reference = $overrides['reference'] ?? 'TOPUP-DEFAULT-REF';
        $amount = $overrides['amount'] ?? 100000;
        $currency = $overrides['currency'] ?? 'VND';

        return PaymentIntent::create(array_merge([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'wallet_topup',
            'target_type' => 'wallet',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'qr_payload' => "BANKQR|BILLINGV3|{$reference}|{$amount}|{$currency}",
            'meta' => [],
            'expires_at' => now()->addMinutes(30),
        ], $overrides));
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

    private function signedBankWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'test-bank-secret');

        return $this->call('POST', '/webhooks/bank/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_BILLING_SIGNATURE' => $signature,
        ], $body);
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function financeUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('finance');

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
