@extends('layouts.admin', ['title' => 'Admin Invoice '.$invoice->invoice_number])
@section('content')
<div class="panel">
    <p><a href="/admin/invoices">Back to Invoices</a></p>
    <h1>{{ $invoice->invoice_number }}</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $invoice->id }}</td></tr>
            <tr><th>Customer</th><td><a href="/admin/customers/{{ $invoice->user_id }}">{{ $invoice->user->email }}</a></td></tr>
            <tr><th>Status</th><td>{{ $invoice->status }}</td></tr>
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
    <table>
        <thead><tr><th>Transaction</th><th>Reference</th><th>Status</th><th>Amount</th><th>Processed</th></tr></thead>
        <tbody>
            @forelse ($paymentEvents as $event)
                <tr>
                    <td><a href="/admin/payment-events/{{ $event->id }}">{{ $event->provider_transaction_id ?? '-' }}</a></td>
                    <td>{{ $event->reference ?? '-' }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}</td>
                    <td>{{ $event->processed_at?->toDateTimeString() ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No payment events for this invoice.</td></tr>
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
