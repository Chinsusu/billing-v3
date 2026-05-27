@extends('layouts.admin', ['title' => 'Payment Event'])
@section('content')
<x-page-header
    title="Payment Event"
    subtitle="Inspect transaction metadata, wallet linkage, and raw payload."
    eyebrow="Financials"
>
    <x-slot:actions>
        @can('payment_events.update')
            @if ($event->provider === 'manual_admin')
                <a href="/admin/payment-events/{{ $event->id }}/edit" class="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83a.996.996 0 0 0 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29Zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75Z"/></svg>
                    <span>Edit Transaction</span>
                </a>
            @endif
        @endcan
        <a href="/admin/payment-events" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Payment Events</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $event->id }}</td></tr>
            <tr><th>Provider</th><td>{{ $event->provider }}</td></tr>
            <tr><th>Transaction</th><td>{{ $event->provider_transaction_id ?? '-' }}</td></tr>
            <tr><th>Reference</th><td>{{ $event->reference ?? '-' }}</td></tr>
            <tr><th>Status</th><td>{{ $event->status }}</td></tr>
            <tr><th>Signature</th><td>{{ $event->signature_status }}</td></tr>
            <tr><th>Amount</th><td>{{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}</td></tr>
            <tr><th>Processed</th><td>{{ $event->processed_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Customer</th><td>@if ($customer)<a href="/admin/customers/{{ $customer->id }}">{{ $customer->email }}</a>@else - @endif</td></tr>
            <tr><th>Invoice</th><td>@if ($event->invoice)<a href="/admin/invoices/{{ $event->invoice->id }}">{{ $event->invoice->invoice_number }}</a>@else - @endif</td></tr>
            <tr><th>Payment Intent</th><td>{{ $event->paymentIntent?->reference ?? '-' }}</td></tr>
            <tr><th>Wallet</th><td>{{ $event->wallet?->currency ?? '-' }} {{ $event->wallet ? number_format($event->wallet->balance_amount) : '' }}</td></tr>
        </tbody>
    </table>
</div>

@php
    $reconciliation = $event->payload['reconciliation'] ?? null;
    $canReconcileStatus = in_array($event->status, ['unmatched', 'rejected', 'expired'], true);
@endphp

@if ($reconciliation)
<div class="panel">
    <h2>Reconciliation</h2>
    <table>
        <tbody>
            <tr><th>Customer</th><td>{{ $reconciliation['user_email'] ?? '-' }}</td></tr>
            <tr><th>Actor</th><td>{{ $reconciliation['actor_email'] ?? '-' }}</td></tr>
            <tr><th>Wallet</th><td>{{ $reconciliation['wallet_id'] ?? '-' }}</td></tr>
            <tr><th>Ledger Entry</th><td>{{ $reconciliation['ledger_entry_id'] ?? '-' }}</td></tr>
            <tr><th>Reconciled</th><td>{{ $reconciliation['reconciled_at'] ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>
@endif

@can('wallets.adjust')
    @if ($canReconcileStatus)
        <div class="panel">
            <h2>Reconcile to Wallet</h2>
            <p>Amount: {{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}</p>
            <form method="POST" action="/admin/payment-events/{{ $event->id }}/reconcile-wallet">
                @csrf
                <label>Customer Email
                    <input name="user_email" type="email" value="{{ old('user_email', $customer?->email) }}" required>
                </label>
                <button type="submit">Reconcile Payment</button>
            </form>
        </div>
    @endif
@endcan

<div class="panel">
    <h2>Payload</h2>
    <pre>{{ json_encode($event->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
