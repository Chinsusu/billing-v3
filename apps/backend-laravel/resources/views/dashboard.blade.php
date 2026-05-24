@extends('layouts.app', ['title' => 'Customer Dashboard'])
@section('content')
<div class="panel">
    <h1>Customer Dashboard</h1>
    <div class="grid">
        <div class="panel"><strong>{{ number_format($wallet->balance_amount) }} {{ $wallet->currency }}</strong><br>Wallet balance</div>
        <div class="panel"><strong>{{ $openInvoiceCount }}</strong><br>Open invoices</div>
        <div class="panel"><strong>{{ $activeServiceCount }}</strong><br>Active services</div>
    </div>
    <p>
        <a class="button" href="/wallet">Open Wallet</a>
        <a class="button secondary" href="/wallet/top-ups">Wallet Top-ups</a>
        <a class="button secondary" href="/invoices">Invoices</a>
        <a class="button secondary" href="/orders">Orders</a>
        <a class="button secondary" href="/services">Services</a>
    </p>
</div>

<div class="grid">
    <div class="panel">
        <h2>Recent Invoices</h2>
        <table>
            <tbody>
                @forelse ($recentInvoices as $invoice)
                    <tr><td><a href="/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->status }}</td><td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td></tr>
                @empty
                    <tr><td class="muted">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Recent Orders</h2>
        <table>
            <tbody>
                @forelse ($recentOrders as $order)
                    <tr><td><a href="/orders/{{ $order->id }}">{{ $order->order_number }}</a></td><td>{{ $order->status }}</td><td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td></tr>
                @empty
                    <tr><td class="muted">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Recent Services</h2>
        <table>
            <tbody>
                @forelse ($recentServices as $service)
                    <tr><td><a href="/services/{{ $service->id }}">{{ $service->product_name }}</a></td><td>{{ $service->status }}</td></tr>
                @empty
                    <tr><td class="muted">No services yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h2>Recent Top-ups</h2>
        <table>
            <tbody>
                @forelse ($recentTopUps as $topUp)
                    <tr><td><a href="/wallet/top-ups/{{ $topUp->id }}">{{ $topUp->reference }}</a></td><td>{{ $topUp->status }}</td><td>{{ number_format($topUp->amount) }} {{ $topUp->currency }}</td></tr>
                @empty
                    <tr><td class="muted">No top-ups yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
