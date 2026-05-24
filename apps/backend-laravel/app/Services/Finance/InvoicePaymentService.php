<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\LedgerEntry;

class InvoicePaymentService
{
    public function __construct(private readonly WalletService $walletService) {}

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

        return $entry;
    }
}
