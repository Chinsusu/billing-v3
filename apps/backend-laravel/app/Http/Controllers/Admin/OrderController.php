<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientWalletBalance;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProvisioningJob;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Orders\OrderCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'customer' => trim((string) $request->query('customer', '')),
        ];

        return view('admin.orders.index', [
            'orders' => Order::with('user', 'items')
                ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['customer'] !== '', function ($query) use ($filters): void {
                    $query->whereHas('user', fn ($query) => $query->where('email', 'like', '%'.$filters['customer'].'%'));
                })
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => ['pending', 'paid', 'failed', 'cancelled'],
        ]);
    }

    public function create(): View
    {
        return view('admin.orders.create', [
            'customers' => User::role('customer')
                ->with(['wallets' => fn ($query) => $query->orderBy('currency')])
                ->orderBy('email')
                ->get(),
            'products' => Product::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, OrderCheckoutService $checkoutService, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('status', 'active')],
        ]);

        $customer = User::role('customer')->whereKey($validated['customer_id'])->first();
        if (! $customer) {
            return back()->withErrors(['customer_id' => 'Select a customer account.'])->withInput();
        }

        $product = Product::whereKey($validated['product_id'])
            ->where('status', 'active')
            ->firstOrFail();

        try {
            $order = $checkoutService->checkout($customer, $product);
        } catch (InsufficientWalletBalance) {
            return back()->withErrors(['wallet' => 'Wallet balance is not enough to create this order.'])->withInput();
        }

        $audit->record(
            $request->user(),
            'admin_order_created',
            $order,
            [],
            [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'customer_id' => $customer->id,
            ],
            [
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
            ],
            $request,
            $order->order_number,
        );

        return redirect("/admin/orders/{$order->id}")->with('status', 'Order created, paid, and queued for provisioning.');
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items', 'services']);

        return view('admin.orders.show', [
            'order' => $order,
            'provisioningJobs' => ProvisioningJob::with('service')
                ->where('order_id', $order->id)
                ->latest()
                ->get(),
            'ledgerEntries' => LedgerEntry::where('source_type', 'order')
                ->where('source_id', $order->id)
                ->latest()
                ->get(),
        ]);
    }
}
