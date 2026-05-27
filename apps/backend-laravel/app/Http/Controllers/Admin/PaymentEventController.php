<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateManualPaymentEventRequest;
use App\Models\PaymentEvent;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PaymentEventController extends Controller
{
    private const AUDIT_FIELDS = ['provider_transaction_id', 'reference', 'processed_at'];

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
            'statuses' => ['accepted', 'rejected', 'unmatched', 'expired', 'reconciled'],
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

    public function edit(PaymentEvent $paymentEvent): View
    {
        abort_unless($paymentEvent->provider === 'manual_admin', 403);

        $paymentEvent->load(['paymentIntent.user', 'wallet.user', 'invoice.user']);

        return view('admin.payment-events.edit', [
            'event' => $paymentEvent,
            'customer' => $paymentEvent->paymentIntent?->user
                ?? $paymentEvent->wallet?->user
                ?? $paymentEvent->invoice?->user,
        ]);
    }

    public function update(UpdateManualPaymentEventRequest $request, PaymentEvent $paymentEvent, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $before = $audit->snapshot($paymentEvent, self::AUDIT_FIELDS);

        $paymentEvent->update([
            'provider_transaction_id' => filled($validated['provider_transaction_id'] ?? null) ? trim($validated['provider_transaction_id']) : null,
            'reference' => filled($validated['reference'] ?? null) ? trim($validated['reference']) : null,
            'processed_at' => filled($validated['processed_at'] ?? null) ? Carbon::parse($validated['processed_at']) : null,
        ]);

        if ($paymentEvent->invoice?->status === 'paid') {
            $paymentEvent->invoice->forceFill(['paid_at' => $paymentEvent->processed_at])->save();
        }

        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($paymentEvent, self::AUDIT_FIELDS));

        if ($beforeChanges !== [] || $afterChanges !== []) {
            $audit->record(
                $request->user(),
                'payment_event_updated',
                $paymentEvent,
                $beforeChanges,
                $afterChanges,
                [],
                $request,
                $paymentEvent->provider_transaction_id ?? $paymentEvent->reference,
            );
        }

        return redirect("/admin/payment-events/{$paymentEvent->id}")->with('status', 'Payment event updated.');
    }
}
