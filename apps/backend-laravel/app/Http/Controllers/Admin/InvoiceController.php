<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('admin.invoices.index', [
            'invoices' => Invoice::with('user')->latest()->paginate(20),
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
