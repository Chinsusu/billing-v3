<?php

namespace Database\Factories;

use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    public function definition(): array
    {
        $reference = 'TOPUP-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));

        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'type' => 'wallet_topup',
            'target_type' => 'wallet',
            'reference' => $reference,
            'amount' => 100000,
            'currency' => 'VND',
            'status' => 'pending',
            'qr_payload' => "BANKQR|BILLINGV3|{$reference}|100000|VND",
            'meta' => [],
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
