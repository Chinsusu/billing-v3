<?php

namespace App\Services\Finance;

use App\Exceptions\InsufficientWalletBalance;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public function walletFor(User $user, string $currency = 'VND'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => strtoupper($currency)],
            ['balance_amount' => 0]
        );
    }

    public function credit(
        Wallet $wallet,
        int $amount,
        string $currency,
        string $sourceType,
        ?string $sourceId,
        string $idempotencyKey,
        string $description,
        array $meta = []
    ): LedgerEntry {
        return $this->move($wallet, 'credit', $amount, $currency, $sourceType, $sourceId, $idempotencyKey, $description, $meta);
    }

    public function debit(
        Wallet $wallet,
        int $amount,
        string $currency,
        string $sourceType,
        ?string $sourceId,
        string $idempotencyKey,
        string $description,
        array $meta = []
    ): LedgerEntry {
        return $this->move($wallet, 'debit', $amount, $currency, $sourceType, $sourceId, $idempotencyKey, $description, $meta);
    }

    private function move(
        Wallet $wallet,
        string $direction,
        int $amount,
        string $currency,
        string $sourceType,
        ?string $sourceId,
        string $idempotencyKey,
        string $description,
        array $meta
    ): LedgerEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Wallet movement amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $direction, $amount, $currency, $sourceType, $sourceId, $idempotencyKey, $description, $meta): LedgerEntry {
            $existing = LedgerEntry::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }

            $lockedWallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $currency = strtoupper($currency);

            if ($lockedWallet->currency !== $currency) {
                throw new InvalidArgumentException('Wallet currency mismatch.');
            }

            $balanceAfter = $direction === 'credit'
                ? $lockedWallet->balance_amount + $amount
                : $lockedWallet->balance_amount - $amount;

            if ($balanceAfter < 0) {
                throw new InsufficientWalletBalance('Wallet balance is not enough to pay this invoice.');
            }

            $lockedWallet->update(['balance_amount' => $balanceAfter]);

            return LedgerEntry::create([
                'wallet_id' => $lockedWallet->id,
                'user_id' => $lockedWallet->user_id,
                'direction' => $direction,
                'amount' => $amount,
                'currency' => $currency,
                'balance_after' => $balanceAfter,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'meta' => $meta,
            ]);
        });
    }
}
