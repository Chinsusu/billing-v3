<?php

namespace App\Services\Finance;

use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Support\Str;

class PaymentIntentService
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function createWalletTopUp(User $user, int $amount, string $currency = 'VND'): PaymentIntent
    {
        $currency = strtoupper($currency);
        $wallet = $this->walletService->walletFor($user, $currency);
        $reference = $this->newReference();

        return PaymentIntent::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'wallet_topup',
            'target_type' => 'wallet',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'qr_payload' => $this->qrPayload($reference, $amount, $currency),
            'meta' => [],
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function newReference(): string
    {
        do {
            $reference = 'TOPUP-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (PaymentIntent::where('reference', $reference)->exists());

        return $reference;
    }

    private function qrPayload(string $reference, int $amount, string $currency): string
    {
        return "BANKQR|BILLINGV3|{$reference}|{$amount}|{$currency}";
    }
}
