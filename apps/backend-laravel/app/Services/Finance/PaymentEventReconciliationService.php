<?php

namespace App\Services\Finance;

use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentEventReconciliationService
{
    private const RECONCILABLE_STATUSES = ['unmatched', 'rejected', 'expired'];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly NotificationOutbox $notifications,
    ) {}

    public function reconcile(PaymentEvent $event, User $customer, ?User $actor): LedgerEntry
    {
        return DB::transaction(function () use ($event, $customer, $actor): LedgerEntry {
            $event = PaymentEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! in_array($event->status, self::RECONCILABLE_STATUSES, true)) {
                throw new InvalidArgumentException('This payment event cannot be reconciled.');
            }

            if ($event->amount === null || $event->amount <= 0 || blank($event->currency)) {
                throw new InvalidArgumentException('This payment event does not have a valid amount and currency.');
            }

            $currency = strtoupper($event->currency);
            $wallet = $this->wallets->walletFor($customer, $currency);
            $idempotencyKey = "payment-event-reconcile:{$event->id}";
            $ledger = $this->wallets->credit(
                $wallet,
                $event->amount,
                $currency,
                'payment_event_reconciliation',
                $event->id,
                $idempotencyKey,
                "Payment event reconciliation {$event->reference}",
                [
                    'actor_id' => $actor?->id,
                    'actor_email' => $actor?->email,
                    'payment_event_id' => $event->id,
                    'provider' => $event->provider,
                    'provider_transaction_id' => $event->provider_transaction_id,
                    'reference' => $event->reference,
                ],
            );

            $payload = $event->payload ?? [];
            $payload['reconciliation'] = [
                'actor_id' => $actor?->id,
                'actor_email' => $actor?->email,
                'user_id' => $customer->id,
                'user_email' => $customer->email,
                'wallet_id' => $wallet->id,
                'ledger_entry_id' => $ledger->id,
                'reconciled_at' => now()->toISOString(),
            ];

            $event->update([
                'status' => 'reconciled',
                'wallet_id' => $wallet->id,
                'payload' => $payload,
            ]);

            $this->notifications->enqueue(
                $customer,
                'wallet_credited',
                $customer->email,
                'Wallet credited',
                "Your wallet was credited {$event->amount} {$currency} from payment reconciliation {$event->reference}.",
                'payment_event',
                $event->id,
                "wallet-credited:payment-event:{$event->id}",
                [
                    'payment_event_id' => $event->id,
                    'ledger_entry_id' => $ledger->id,
                    'amount' => $event->amount,
                    'currency' => $currency,
                ],
            );

            return $ledger;
        });
    }
}
