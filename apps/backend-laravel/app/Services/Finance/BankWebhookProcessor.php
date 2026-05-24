<?php

namespace App\Services\Finance;

use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use Illuminate\Support\Carbon;

class BankWebhookProcessor
{
    public function __construct(private readonly WalletService $walletService) {}

    public function handle(string $body, ?string $signature): array
    {
        if (! $this->hasValidSignature($body, $signature)) {
            return [
                'status_code' => 401,
                'body' => ['status' => 'invalid_signature'],
            ];
        }

        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $reference = (string) ($payload['reference'] ?? '');
        $amount = (int) ($payload['amount'] ?? 0);
        $currency = strtoupper((string) ($payload['currency'] ?? ''));
        $transactionId = (string) ($payload['transaction_id'] ?? '');
        $paidAt = (string) ($payload['paid_at'] ?? '');

        if ($transactionId !== '' && PaymentEvent::where('provider', 'bank_sandbox')->where('provider_transaction_id', $transactionId)->exists()) {
            return [
                'status_code' => 200,
                'body' => ['status' => 'duplicate'],
            ];
        }

        $intent = PaymentIntent::where('reference', $reference)->first();

        if (! $intent) {
            PaymentEvent::create($this->eventAttributes(null, $payload, 'unmatched', $reference, $amount, $currency, $transactionId));

            return [
                'status_code' => 202,
                'body' => ['status' => 'unmatched'],
            ];
        }

        if ($intent->amount !== $amount || $intent->currency !== $currency) {
            PaymentEvent::create($this->eventAttributes($intent, $payload, 'rejected', $reference, $amount, $currency, $transactionId));

            return [
                'status_code' => 422,
                'body' => ['status' => 'rejected'],
            ];
        }

        if ($this->intentIsExpired($intent)) {
            if ($intent->status === 'pending') {
                $intent->update(['status' => 'expired']);
            }

            PaymentEvent::create($this->eventAttributes($intent, $payload, 'expired', $reference, $amount, $currency, $transactionId));

            return [
                'status_code' => 422,
                'body' => ['status' => 'expired'],
            ];
        }

        if ($intent->status !== 'pending') {
            PaymentEvent::create($this->eventAttributes($intent, $payload, 'rejected', $reference, $amount, $currency, $transactionId));

            return [
                'status_code' => 422,
                'body' => ['status' => 'rejected'],
            ];
        }

        $event = PaymentEvent::create($this->eventAttributes($intent, $payload, 'accepted', $reference, $amount, $currency, $transactionId));
        $wallet = $intent->wallet ?? $this->walletService->walletFor($intent->user, $currency);

        $this->walletService->credit(
            $wallet,
            $amount,
            $currency,
            'payment_intent',
            $intent->id,
            "bank:{$transactionId}",
            "Bank sandbox top-up {$reference}",
            ['payment_event_id' => $event->id]
        );

        $intent->update([
            'status' => 'succeeded',
            'paid_at' => $paidAt !== '' ? Carbon::parse($paidAt) : now(),
        ]);

        return [
            'status_code' => 200,
            'body' => ['status' => 'accepted'],
        ];
    }

    private function hasValidSignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.bank_sandbox.webhook_secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    private function intentIsExpired(PaymentIntent $intent): bool
    {
        return $intent->status === 'expired'
            || ($intent->status === 'pending' && $intent->expires_at !== null && $intent->expires_at->isPast());
    }

    private function eventAttributes(
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
            'provider' => 'bank_sandbox',
            'provider_transaction_id' => $transactionId !== '' ? $transactionId : null,
            'reference' => $reference !== '' ? $reference : null,
            'amount' => $amount > 0 ? $amount : null,
            'currency' => $currency !== '' ? $currency : null,
            'status' => $status,
            'signature_status' => 'valid',
            'payload' => $payload,
            'processed_at' => now(),
        ];
    }
}
