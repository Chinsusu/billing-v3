<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceStatusRequest;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\ManualInvoicePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    private const AUDIT_FIELDS = ['status', 'paid_at'];

    public function create(): View
    {
        return view('admin.invoices.create', [
            'customers' => User::role('customer')
                ->orderBy('email')
                ->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'customer' => trim((string) $request->query('customer', '')),
        ];

        return view('admin.invoices.index', [
            'invoices' => Invoice::with('user')
                ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['customer'] !== '', function ($query) use ($filters): void {
                    $query->whereHas('user', fn ($query) => $query->where('email', 'like', '%'.$filters['customer'].'%'));
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => ['open', 'paid', 'void'],
            'customers' => User::role('customer')
                ->orderBy('email')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('user');
        $paymentEvents = PaymentEvent::with(['paymentIntent', 'wallet'])
            ->where('invoice_id', $invoice->id)
            ->latest()
            ->get();
        $paymentEventIds = $paymentEvents->pluck('id');
        $paymentIntentIds = PaymentIntent::where('invoice_id', $invoice->id)->pluck('id');

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'paymentEvents' => $paymentEvents,
            'ledgerEntries' => LedgerEntry::query()
                ->where(function ($query) use ($invoice, $paymentEventIds, $paymentIntentIds): void {
                    $query
                        ->where(fn ($query) => $query->where('source_type', 'invoice')->where('source_id', $invoice->id))
                        ->orWhere(fn ($query) => $query->where('source_type', 'manual_invoice_payment')->where('source_id', $invoice->id));

                    if ($paymentIntentIds->isNotEmpty()) {
                        $query->orWhere(fn ($query) => $query->where('source_type', 'payment_intent')->whereIn('source_id', $paymentIntentIds));
                    }

                    if ($paymentEventIds->isNotEmpty()) {
                        $query->orWhere(fn ($query) => $query->whereIn('source_type', ['payment_event', 'payment_event_reconciliation'])->whereIn('source_id', $paymentEventIds));
                    }
                })
                ->latest()
                ->get(),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        $invoice->load('user');

        return view('admin.invoices.edit', [
            'invoice' => $invoice,
            'manualPaymentEvent' => PaymentEvent::where('provider', ManualInvoicePaymentService::PROVIDER)
                ->where('invoice_id', $invoice->id)
                ->first(),
            'statuses' => ['open', 'paid', 'void'],
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['user_email'])->firstOrFail();
        $amount = (int) $validated['total_amount'];

        Invoice::create([
            'user_id' => $user->id,
            'invoice_number' => $this->newInvoiceNumber(),
            'status' => 'open',
            'total_amount' => $amount,
            'currency' => $validated['currency'],
            'description' => $validated['description'],
            'lines' => [
                ['description' => $validated['description'], 'amount' => $amount],
            ],
        ]);

        return redirect('/admin/invoices')->with('status', 'Invoice created.');
    }

    public function update(
        UpdateInvoiceStatusRequest $request,
        Invoice $invoice,
        AuditLogger $audit,
        ManualInvoicePaymentService $manualPayments,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = $audit->snapshot($invoice, self::AUDIT_FIELDS);

        DB::transaction(function () use ($audit, $before, $invoice, $manualPayments, $request, $validated): void {
            if ($validated['status'] === 'paid') {
                $manualPayments->record($invoice, $request->user(), $validated);
                $invoice->refresh();
            } else {
                $invoice->forceFill([
                    'status' => $validated['status'],
                    'paid_at' => null,
                ])->save();
            }

            [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($invoice, self::AUDIT_FIELDS));

            if ($beforeChanges !== [] || $afterChanges !== []) {
                $audit->record(
                    $request->user(),
                    'invoice_status_updated',
                    $invoice,
                    $beforeChanges,
                    $afterChanges,
                    [],
                    $request,
                    $invoice->invoice_number,
                );
            }
        });

        return redirect("/admin/invoices/{$invoice->id}")->with('status', 'Invoice status updated.');
    }

    private function newInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.str_pad((string) (Invoice::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
