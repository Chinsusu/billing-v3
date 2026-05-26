<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\ProvisioningJob;
use Illuminate\Http\Request;
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
