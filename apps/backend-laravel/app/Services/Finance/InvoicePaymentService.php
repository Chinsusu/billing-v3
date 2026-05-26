<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Services\Notifications\NotificationOutbox;

class InvoicePaymentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly NotificationOutbox $notifications,
    ) {}

    public function payFromWallet(Invoice $invoice): LedgerEntry
    {
        $existing = LedgerEntry::where('idempotency_key', "invoice-payment:{$invoice->id}")->first();

        if ($existing) {
            return $existing;
        }

        $wallet = $this->walletService->walletFor($invoice->user, $invoice->currency);

        $entry = $this->walletService->debit(
            $wallet,
            $invoice->total_amount,
            $invoice->currency,
            'invoice',
            $invoice->id,
            "invoice-payment:{$invoice->id}",
            "Invoice {$invoice->invoice_number}",
            ['invoice_number' => $invoice->invoice_number]
        );

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->notifications->enqueue(
            $invoice->user,
            'invoice_paid',
            $invoice->user->email,
            'Invoice paid',
            "Invoice {$invoice->invoice_number} was paid.",
            'invoice',
            $invoice->id,
            "invoice-paid:{$invoice->id}",
            [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'ledger_entry_id' => $entry->id,
                'amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
            ],
        );

        return $entry;
    }
}
