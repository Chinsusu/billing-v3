@extends('layouts.app', ['title' => 'Customer'])
@section('content')
<div class="panel">
    <h1>{{ $customer->name }}</h1>
    <p>{{ $customer->email }}</p>
    <p>
        <a class="button secondary" href="/admin/customers">Back to Customers</a>
        @can('customers.view')
            <form method="POST" action="/admin/customers/{{ $customer->id }}/impersonate" style="display:inline">
                @csrf
                <button type="submit">Impersonate</button>
            </form>
        @endcan
    </p>
</div>

<div class="panel">
    <h2>Reseller</h2>
    <p>Current: {{ $customer->reseller?->email ?? 'Direct customer' }}</p>
    <form method="POST" action="/admin/customers/{{ $customer->id }}/reseller">
        @csrf
        <label for="reseller_id">Assigned reseller</label>
        <select id="reseller_id" name="reseller_id">
            <option value="">Direct customer</option>
            @foreach ($resellers as $reseller)
                <option value="{{ $reseller->id }}" @selected($customer->reseller_id === $reseller->id)>{{ $reseller->email }}</option>
            @endforeach
        </select>
        <button type="submit">Save Reseller</button>
    </form>
</div>

<div class="panel">
    <h2>Wallets</h2>
    <table>
        <thead><tr><th>Currency</th><th>Balance</th></tr></thead>
        <tbody>
            @forelse ($wallets as $wallet)
                <tr><td>{{ $wallet->currency }}</td><td>{{ number_format($wallet->balance_amount) }} {{ $wallet->currency }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No wallets yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@can('wallets.adjust')
<div class="panel">
    <h2>Manual Adjustment</h2>
    <form method="POST" action="/admin/customers/{{ $customer->id }}/wallet-adjustments">
        @csrf
        <label>Direction
            <select name="direction">
                <option value="credit">credit</option>
                <option value="debit">debit</option>
            </select>
        </label>
        <label>Amount<input name="amount" type="number" min="1" required></label>
        <label>Currency<input name="currency" value="VND" maxlength="3" required></label>
        <label>Reason<textarea name="reason" rows="3" required></textarea></label>
        <label>Reference<input name="reference" placeholder="Optional idempotency reference"></label>
        <button type="submit">Record Adjustment</button>
    </form>
</div>
@endcan

<div class="panel">
    <h2>Recent Ledger</h2>
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
                <tr><td colspan="6" class="muted">No ledger entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Invoices</h2>
    <table>
        <thead><tr><th>Invoice</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr><td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->status }}</td><td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Orders</h2>
    <table>
        <thead><tr><th>Order</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($orders as $order)
                <tr><td><a href="/admin/orders/{{ $order->id }}">{{ $order->order_number }}</a></td><td>{{ $order->status }}</td><td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Services</h2>
    <table>
        <thead><tr><th>Product</th><th>Status</th><th>Expires</th></tr></thead>
        <tbody>
            @forelse ($services as $service)
                <tr><td>{{ $service->product_name }}</td><td>{{ $service->status }}</td><td>{{ $service->expires_at?->format('Y-m-d') ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No services yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
