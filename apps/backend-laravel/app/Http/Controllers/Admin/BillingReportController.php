<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CsvResponseFactory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillingReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.billing', [
            'invoiceCount' => Invoice::count(),
            'ledgerCount' => LedgerEntry::count(),
            'paymentEventCount' => PaymentEvent::count(),
        ]);
    }

    public function invoices(Request $request, CsvResponseFactory $csv, AuditLogger $auditLogger): Response
    {
        $this->auditExport($request, $auditLogger, 'invoices');

        $rows = Invoice::with('user')->latest()->get()->map(fn (Invoice $invoice): array => [
            $invoice->invoice_number,
            $invoice->user?->email,
            $invoice->status,
            $invoice->total_amount,
            $invoice->currency,
            $invoice->due_at,
            $invoice->paid_at,
            $invoice->created_at,
        ]);

        return $csv->make('billing-invoices.csv', [
            'invoice_number',
            'customer_email',
            'status',
            'total_amount',
            'currency',
            'due_at',
            'paid_at',
            'created_at',
        ], $rows);
    }

    public function ledger(Request $request, CsvResponseFactory $csv, AuditLogger $auditLogger): Response
    {
        $this->auditExport($request, $auditLogger, 'ledger');

        $rows = LedgerEntry::with('user')->latest()->get()->map(fn (LedgerEntry $entry): array => [
            $entry->id,
            $entry->user?->email,
            $entry->direction,
            $entry->amount,
            $entry->currency,
            $entry->balance_after,
            $entry->source_type,
            $entry->source_id,
            $entry->description,
            $entry->created_at,
        ]);

        return $csv->make('billing-ledger.csv', [
            'id',
            'customer_email',
            'direction',
            'amount',
            'currency',
            'balance_after',
            'source_type',
            'source_id',
            'description',
            'created_at',
        ], $rows);
    }

    public function paymentEvents(Request $request, CsvResponseFactory $csv, AuditLogger $auditLogger): Response
    {
        $this->auditExport($request, $auditLogger, 'payment-events');

        $rows = PaymentEvent::latest()->get()->map(fn (PaymentEvent $event): array => [
            $event->id,
            $event->provider,
            $event->provider_transaction_id,
            $event->reference,
            $event->amount,
            $event->currency,
            $event->status,
            $event->signature_status,
            $event->processed_at,
            $event->created_at,
        ]);

        return $csv->make('billing-payment-events.csv', [
            'id',
            'provider',
            'provider_transaction_id',
            'reference',
            'amount',
            'currency',
            'status',
            'signature_status',
            'processed_at',
            'created_at',
        ], $rows);
    }

    private function auditExport(Request $request, AuditLogger $auditLogger, string $report): void
    {
        $auditLogger->record($request->user(), 'billing_report_exported', $request->user(), [], [], [
            'report' => $report,
        ], $request, $report);
    }
}
