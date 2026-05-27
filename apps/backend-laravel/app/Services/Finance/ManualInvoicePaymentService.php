<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManualInvoicePaymentService
{
    public const PROVIDER = 'manual_admin';

    public function __construct(
        private readonly WalletService $wallets,
        private readonly NotificationOutbox $notifications,
    ) {}

    /**
     * @param  array{provider_transaction_id?: ?string, payment_reference?: ?string, processed_at?: ?string, paid_at?: ?string}  $transaction
     * @return array{event: PaymentEvent, ledger: LedgerEntry}
     */
    public function record(Invoice $invoice, ?User $actor, array $transaction = []): array
    {
        return DB::transaction(function () use ($actor, $invoice, $transaction): array {
            $invoice = Invoice::with('user')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $currency = strtoupper($invoice->currency);
            $wallet = $this->wallets->walletFor($invoice->user, $currency);
            $event = PaymentEvent::where('provider', self::PROVIDER)
                ->where('invoice_id', $invoice->id)
                ->lockForUpdate()
                ->first();
            $fallbackTimestamp = now();
            $processedAt = $this->timestamp($transaction['processed_at'] ?? null, $fallbackTimestamp);
            $paidAt = $this->timestamp($transaction['paid_at'] ?? null, $processedAt);

            $event = $this->upsertEvent($event, $invoice, $wallet, $actor, $transaction, $processedAt);
            $ledger = $this->wallets->credit(
                $wallet,
                $invoice->total_amount,
                $currency,
                'manual_invoice_payment',
                $invoice->id,
                "manual-invoice-payment:{$invoice->id}",
                "Manual invoice top-up {$invoice->invoice_number}",
                [
                    'actor_id' => $actor?->id,
                    'actor_email' => $actor?->email,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_event_id' => $event->id,
                    'provider_transaction_id' => $event->provider_transaction_id,
                    'reference' => $event->reference,
                ],
            );

            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => $paidAt,
            ])->save();

            $this->notifications->enqueue(
                $invoice->user,
                'wallet_credited',
                $invoice->user->email,
                'Wallet credited',
                "Your wallet was credited {$invoice->total_amount} {$currency} for invoice {$invoice->invoice_number}.",
                'invoice',
                $invoice->id,
                "wallet-credited:manual-invoice:{$invoice->id}",
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_event_id' => $event->id,
                    'ledger_entry_id' => $ledger->id,
                    'amount' => $invoice->total_amount,
                    'currency' => $currency,
                ],
            );

            return ['event' => $event->refresh(), 'ledger' => $ledger];
        });
    }

    private function upsertEvent(
        ?PaymentEvent $event,
        Invoice $invoice,
        Wallet $wallet,
        ?User $actor,
        array $transaction,
        Carbon $processedAt,
    ): PaymentEvent {
        $payload = $event?->payload ?? [];
        $payload['manual_admin'] = [
            'actor_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'updated_at' => now()->toISOString(),
        ];

        $attributes = [
            'wallet_id' => $wallet->id,
            'invoice_id' => $invoice->id,
            'provider' => self::PROVIDER,
            'provider_transaction_id' => $this->transactionId($transaction['provider_transaction_id'] ?? null, $invoice),
            'reference' => $this->reference($transaction['payment_reference'] ?? null, $invoice),
            'amount' => $invoice->total_amount,
            'currency' => strtoupper($invoice->currency),
            'status' => 'accepted',
            'signature_status' => 'manual',
            'payload' => $payload,
            'processed_at' => $processedAt,
        ];

        if ($event) {
            $event->update($attributes);

            return $event;
        }

        return PaymentEvent::create($attributes);
    }

    private function transactionId(?string $transactionId, Invoice $invoice): string
    {
        $transactionId = trim((string) $transactionId);

        return $transactionId !== '' ? $transactionId : "MANUAL-{$invoice->invoice_number}";
    }

    private function reference(?string $reference, Invoice $invoice): string
    {
        $reference = trim((string) $reference);

        return $reference !== '' ? $reference : $invoice->invoice_number;
    }

    private function timestamp(?string $timestamp, Carbon $fallback): Carbon
    {
        if (is_string($timestamp) && trim($timestamp) !== '') {
            return Carbon::parse($timestamp);
        }

        return $fallback->copy();
    }
}
