<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
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
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('user');

        return view('admin.invoices.show', [
            'invoice' => $invoice,
            'paymentEvents' => PaymentEvent::with(['paymentIntent', 'wallet'])
                ->where('invoice_id', $invoice->id)
                ->latest()
                ->get(),
            'ledgerEntries' => LedgerEntry::where('source_type', 'invoice')
                ->where('source_id', $invoice->id)
                ->latest()
                ->get(),
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

    private function newInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.str_pad((string) (Invoice::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
