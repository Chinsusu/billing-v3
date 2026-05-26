@extends('layouts.app', ['title' => 'Admin Order '.$order->order_number])
@section('content')
<div class="panel">
    <p><a href="/admin/orders">Back to Orders</a></p>
    <h1>{{ $order->order_number }}</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $order->id }}</td></tr>
            <tr><th>Customer</th><td><a href="/admin/customers/{{ $order->user_id }}">{{ $order->user->email }}</a></td></tr>
            <tr><th>Status</th><td>{{ $order->status }}</td></tr>
            <tr><th>Subtotal</th><td>{{ number_format($order->subtotal_amount) }} {{ $order->currency }}</td></tr>
            <tr><th>Total</th><td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td></tr>
            <tr><th>Paid</th><td>{{ $order->paid_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Created</th><td>{{ $order->created_at?->toDateTimeString() ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Items</h2>
    <table>
        <thead><tr><th>Product</th><th>Code</th><th>Type</th><th>Quantity</th><th>Amount</th><th>Snapshot</th></tr></thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->product_code }}</td>
                    <td>{{ $item->product_type }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->subtotal_amount) }} {{ $item->currency }}</td>
                    <td><pre>{{ json_encode($item->config_snapshot ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No order items.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Services</h2>
    <table>
        <thead><tr><th>Product</th><th>Status</th><th>External ID</th><th>Expires</th><th></th></tr></thead>
        <tbody>
            @forelse ($order->services as $service)
                <tr>
                    <td>{{ $service->product_name }}</td>
                    <td>{{ $service->status }}</td>
                    <td>{{ $service->external_id ?? '-' }}</td>
                    <td>{{ $service->expires_at?->toDateTimeString() ?? '-' }}</td>
                    <td><a class="button secondary" href="/admin/services/{{ $service->id }}">Runbook</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No services for this order.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Provisioning Jobs</h2>
    <table>
        <thead><tr><th>Type</th><th>Status</th><th>Attempts</th><th>Reference</th><th>Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($provisioningJobs as $job)
                <tr>
                    <td>{{ $job->type }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                    <td><a class="button secondary" href="/admin/provisioning-jobs/{{ $job->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No provisioning jobs for this order.</td></tr>
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
                <tr><td colspan="6" class="muted">No ledger entries for this order.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
