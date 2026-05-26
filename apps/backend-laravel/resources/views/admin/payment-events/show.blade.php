@extends('layouts.admin', ['title' => 'Payment Event'])
@section('content')
<div class="panel">
    <p><a href="/admin/payment-events">Back to Payment Events</a></p>
    <h1>Payment Event</h1>
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
