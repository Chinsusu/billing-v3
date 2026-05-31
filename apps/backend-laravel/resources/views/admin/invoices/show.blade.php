@extends('layouts.admin', ['title' => 'Admin Invoice '.$invoice->invoice_number])
@section('content')
<x-page-header
    title="{{ $invoice->invoice_number }}"
    eyebrow="Financials"
>
    <x-slot:actions>
        @can('invoices.update')
            <a href="/admin/invoices/{{ $invoice->id }}/edit" class="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83a.996.996 0 0 0 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29Zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75Z"/></svg>
                <span>Edit Status</span>
            </a>
        @endcan
        <a href="/admin/invoices" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Invoices</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $invoice->id }}</td></tr>
            <tr><th>Customer</th><td><a href="/admin/customers/{{ $invoice->user_id }}">{{ $invoice->user->email }}</a></td></tr>
            <tr><th>Status</th><td><x-status-badge :tone="$invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'neutral' : 'warning')">{{ $invoice->status }}</x-status-badge></td></tr>
            <tr><th>Total</th><td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td></tr>
            <tr><th>Description</th><td>{{ $invoice->description ?? '-' }}</td></tr>
            <tr><th>Due</th><td>{{ $invoice->due_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Paid</th><td>{{ $invoice->paid_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Created</th><td>{{ $invoice->created_at?->toDateTimeString() ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Lines</h2>
    <table>
        <thead><tr><th>Description</th><th>Amount</th></tr></thead>
        <tbody>
            @forelse ($invoice->lines ?? [] as $line)
                <tr>
                    <td>{{ $line['description'] ?? '-' }}</td>
                    <td>{{ isset($line['amount']) ? number_format((int) $line['amount']).' '.$invoice->currency : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="muted">No invoice lines.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Payment Events</h2>
    @php
        $paymentEventColumns = auth()->user()?->can('payment_events.update') ? 6 : 5;
    @endphp
    <table>
        <thead>
            <tr>
                <th>Transaction</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Processed</th>
                @can('payment_events.update')
                    <th>Actions</th>
                @endcan
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentEvents as $event)
                <tr>
                    <td><a href="/admin/payment-events/{{ $event->id }}">{{ $event->provider_transaction_id ?? '-' }}</a></td>
                    <td>{{ $event->reference ?? '-' }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}</td>
                    <td>{{ $event->processed_at?->toDateTimeString() ?? '-' }}</td>
                    @can('payment_events.update')
                        <td>
                            @if ($event->provider === 'manual_admin')
                                <a href="/admin/payment-events/{{ $event->id }}/edit" class="button secondary button-soft button-compact">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83a.996.996 0 0 0 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29Zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75Z"/></svg>
                                    <span>Edit</span>
                                </a>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="{{ $paymentEventColumns }}" class="muted">No payment events for this invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Ledger Entries</h2>
    <table>
        <thead><tr><th>When</th><th>Direction</th><th>Amount</th><th>Balance</th><th>Description</th><th>Source</th></tr></thead>
        <tbody>
            @forelse ($ledgerEntries as $entry)
                <tr>
                    <td>{{ $entry->created_at?->toDateTimeString() }}</td>
                    <td>{{ $entry->direction }}</td>
                    <td>{{ number_format($entry->amount) }} {{ $entry->currency }}</td>
                    <td>{{ number_format($entry->balance_after) }} {{ $entry->currency }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $entry->source_type }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No ledger entries for this invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
