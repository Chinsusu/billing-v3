<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => $request->query('status'),
            'reference' => trim((string) $request->query('reference', '')),
        ];

        return view('admin.payment-events.index', [
            'paymentEvents' => PaymentEvent::query()
                ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['reference'] !== '', fn ($query) => $query->where('reference', 'like', '%'.$filters['reference'].'%'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'statuses' => ['accepted', 'rejected', 'unmatched'],
        ]);
    }

    public function show(PaymentEvent $paymentEvent): View
    {
        $paymentEvent->load(['paymentIntent.user', 'wallet.user', 'invoice.user']);

        return view('admin.payment-events.show', [
            'event' => $paymentEvent,
            'customer' => $paymentEvent->paymentIntent?->user
                ?? $paymentEvent->wallet?->user
                ?? $paymentEvent->invoice?->user,
        ]);
    }
}
