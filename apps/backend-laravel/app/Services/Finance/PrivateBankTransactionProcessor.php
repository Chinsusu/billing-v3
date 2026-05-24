<?php

namespace App\Services\Finance;

use App\Models\BankIntegration;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PrivateBankTransactionProcessor
{
    public function __construct(private readonly WalletService $walletService) {}

    public function process(BankIntegration $integration, array $transaction): string
    {
        $transactionId = (string) ($transaction['transaction_id'] ?? '');
        $reference = (string) ($transaction['reference'] ?? '');
        $amount = (int) ($transaction['amount'] ?? 0);
        $currency = strtoupper((string) ($transaction['currency'] ?? ''));
        $paidAt = (string) ($transaction['paid_at'] ?? '');

        if ($transactionId !== '' && PaymentEvent::where('provider', $integration->provider)->where('provider_transaction_id', $transactionId)->exists()) {
            return 'duplicate';
        }

        return DB::transaction(function () use ($integration, $transaction, $transactionId, $reference, $amount, $currency, $paidAt): string {
            $intent = PaymentIntent::where('reference', $reference)->first();

            if (! $intent) {
                PaymentEvent::create($this->eventAttributes($integration, null, $transaction, 'unmatched', $reference, $amount, $currency, $transactionId));

                return 'unmatched';
            }

            if ($intent->amount !== $amount || $intent->currency !== $currency) {
                PaymentEvent::create($this->eventAttributes($integration, $intent, $transaction, 'rejected', $reference, $amount, $currency, $transactionId));

                return 'rejected';
            }

            if ($this->intentIsExpired($intent)) {
                if ($intent->status === 'pending') {
                    $intent->update(['status' => 'expired']);
                }

                PaymentEvent::create($this->eventAttributes($integration, $intent, $transaction, 'expired', $reference, $amount, $currency, $transactionId));

                return 'expired';
            }

            if ($intent->status !== 'pending') {
                PaymentEvent::create($this->eventAttributes($integration, $intent, $transaction, 'rejected', $reference, $amount, $currency, $transactionId));

                return 'rejected';
            }

            $event = PaymentEvent::create($this->eventAttributes($integration, $intent, $transaction, 'accepted', $reference, $amount, $currency, $transactionId));
            $wallet = $intent->wallet ?? $this->walletService->walletFor($intent->user, $currency);

            $this->walletService->credit(
                $wallet,
                $amount,
                $currency,
                'payment_intent',
                $intent->id,
                "{$integration->provider}:{$transactionId}",
                "Private bank top-up {$reference}",
                ['payment_event_id' => $event->id, 'bank_integration_id' => $integration->id]
            );

            $intent->update([
                'status' => 'succeeded',
                'paid_at' => $paidAt !== '' ? Carbon::parse($paidAt) : now(),
            ]);

            return 'accepted';
        });
    }

    private function intentIsExpired(PaymentIntent $intent): bool
    {
        return $intent->status === 'expired'
            || ($intent->status === 'pending' && $intent->expires_at !== null && $intent->expires_at->isPast());
    }

    private function eventAttributes(
        BankIntegration $integration,
        ?PaymentIntent $intent,
        array $payload,
        string $status,
        string $reference,
        int $amount,
        string $currency,
        string $transactionId
    ): array {
        return [
            'payment_intent_id' => $intent?->id,
            'wallet_id' => $intent?->wallet_id,
            'invoice_id' => $intent?->invoice_id,
            'provider' => $integration->provider,
            'provider_transaction_id' => $transactionId !== '' ? $transactionId : null,
            'reference' => $reference !== '' ? $reference : null,
            'amount' => $amount > 0 ? $amount : null,
            'currency' => $currency !== '' ? $currency : null,
            'status' => $status,
            'signature_status' => 'valid',
            'payload' => $payload + ['bank_integration_id' => $integration->id],
            'processed_at' => now(),
        ];
    }
}
