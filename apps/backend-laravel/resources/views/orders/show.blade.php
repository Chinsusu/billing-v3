@extends('layouts.app', ['title' => 'Order '.$order->order_number])
@section('content')
<div class="panel">
    <h1>{{ $order->order_number }}</h1>
    <p>Status: <strong>{{ $order->status }}</strong></p>
    <p>Total: <strong>{{ number_format($order->total_amount) }} {{ $order->currency }}</strong></p>
</div>

<div class="panel">
    <h2>Items</h2>
    <table>
        <thead><tr><th>Product</th><th>Type</th><th>Amount</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->product_type }}</td>
                    <td>{{ number_format($item->subtotal_amount) }} {{ $item->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Services</h2>
    <table>
        <thead><tr><th>Product</th><th>Status</th><th>Expires</th></tr></thead>
        <tbody>
            @foreach ($order->services as $service)
                <tr>
                    <td>{{ $service->product_name }}</td>
                    <td>{{ $service->status }}</td>
                    <td>{{ $service->expires_at?->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
