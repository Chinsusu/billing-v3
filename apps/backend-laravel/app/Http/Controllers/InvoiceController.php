<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalance;
use App\Models\Invoice;
use App\Services\Finance\InvoicePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        return view('invoices.show', ['invoice' => $invoice]);
    }

    public function pay(Request $request, Invoice $invoice, InvoicePaymentService $invoicePaymentService): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 404);

        if ($invoice->status !== 'open') {
            return redirect("/invoices/{$invoice->id}");
        }

        try {
            $invoicePaymentService->payFromWallet($invoice);
        } catch (InsufficientWalletBalance $exception) {
            return redirect("/invoices/{$invoice->id}")->withErrors(['wallet' => $exception->getMessage()]);
        }

        return redirect("/invoices/{$invoice->id}")->with('status', 'Invoice paid.');
    }
}
