<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTopUpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_wallet_top_up_intent(): void
    {
        $user = $this->customerUser();

        $response = $this->actingAs($user)->post('/wallet/top-ups', [
            'amount' => 150000,
            'currency' => 'VND',
        ]);

        $intent = PaymentIntent::firstOrFail();

        $response->assertRedirect("/wallet/top-ups/{$intent->id}");
        $this->assertSame($user->id, $intent->user_id);
        $this->assertSame('wallet_topup', $intent->type);
        $this->assertSame('pending', $intent->status);
        $this->assertSame(150000, $intent->amount);
        $this->assertSame('VND', $intent->currency);
        $this->assertStringStartsWith('TOPUP-', $intent->reference);
        $this->assertStringContainsString($intent->reference, $intent->qr_payload);
        $this->assertStringContainsString('150000', $intent->qr_payload);

        $this->actingAs($user)
            ->get("/wallet/top-ups/{$intent->id}")
            ->assertOk()
            ->assertSee($intent->reference)
            ->assertSee('150,000 VND');
    }

    public function test_signed_bank_webhook_credits_wallet_once(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'test-bank-secret']);
        $user = $this->customerUser();
        $this->actingAs($user)->post('/wallet/top-ups', [
            'amount' => 150000,
            'currency' => 'VND',
        ]);
        $intent = PaymentIntent::firstOrFail();

        $payload = [
            'reference' => $intent->reference,
            'amount' => 150000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-TXN-0001',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ];

        $this->signedBankWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance_amount' => 150000,
            'currency' => 'VND',
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'credit',
            'amount' => 150000,
            'balance_after' => 150000,
            'idempotency_key' => 'bank:BANK-TXN-0001',
        ]);

        $this->signedBankWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertSame(150000, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertSame(1, PaymentEvent::count());
    }

    public function test_invalid_bank_webhook_signature_is_rejected_without_mutation(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'test-bank-secret']);
        $user = $this->customerUser();
        $this->actingAs($user)->post('/wallet/top-ups', [
            'amount' => 50000,
            'currency' => 'VND',
        ]);
        $intent = PaymentIntent::firstOrFail();

        $payload = [
            'reference' => $intent->reference,
            'amount' => 50000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-TXN-BAD-SIG',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ];

        $this->rawBankWebhook($payload, 'invalid-signature')->assertUnauthorized();

        $this->assertSame(0, PaymentEvent::count());
        $this->assertSame(0, LedgerEntry::count());
        $this->assertSame(0, Wallet::firstOrFail()->balance_amount);
    }

    public function test_unknown_bank_reference_is_recorded_as_unmatched(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'test-bank-secret']);

        $payload = [
            'reference' => 'TOPUP-MISSING',
            'amount' => 50000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-TXN-UNMATCHED',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ];

        $this->signedBankWebhook($payload)
            ->assertAccepted()
            ->assertJsonPath('status', 'unmatched');

        $this->assertDatabaseHas('payment_events', [
            'reference' => 'TOPUP-MISSING',
            'provider_transaction_id' => 'BANK-TXN-UNMATCHED',
            'status' => 'unmatched',
        ]);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_amount_mismatch_is_rejected_without_wallet_credit(): void
    {
        config(['services.bank_sandbox.webhook_secret' => 'test-bank-secret']);
        $user = $this->customerUser();
        $this->actingAs($user)->post('/wallet/top-ups', [
            'amount' => 50000,
            'currency' => 'VND',
        ]);
        $intent = PaymentIntent::firstOrFail();

        $payload = [
            'reference' => $intent->reference,
            'amount' => 60000,
            'currency' => 'VND',
            'transaction_id' => 'BANK-TXN-MISMATCH',
            'paid_at' => '2026-05-24T09:00:00+07:00',
        ];

        $this->signedBankWebhook($payload)
            ->assertUnprocessable()
            ->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('payment_events', [
            'provider_transaction_id' => 'BANK-TXN-MISMATCH',
            'status' => 'rejected',
        ]);
        $this->assertSame(0, Wallet::firstOrFail()->balance_amount);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_customer_can_view_wallet_balance_and_ledger(): void
    {
        $user = $this->customerUser();
        $wallet = Wallet::factory()->for($user)->create(['balance_amount' => 200000]);
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'direction' => 'credit',
            'amount' => 200000,
            'currency' => 'VND',
            'balance_after' => 200000,
            'source_type' => 'seed',
            'idempotency_key' => 'seed-wallet-view',
            'description' => 'Seed credit',
            'meta' => [],
        ]);

        $this->actingAs($user)
            ->get('/wallet')
            ->assertOk()
            ->assertSee('200,000 VND')
            ->assertSee('Seed credit');
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function signedBankWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'test-bank-secret');

        return $this->rawBankWebhook($payload, $signature);
    }

    private function rawBankWebhook(array $payload, string $signature)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/bank/sandbox', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_BILLING_SIGNATURE' => $signature,
        ], $body);
    }
}
