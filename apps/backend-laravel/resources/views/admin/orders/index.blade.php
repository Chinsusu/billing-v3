@extends('layouts.app', ['title' => 'Admin Orders'])
@section('content')
<div class="panel">
    <h1>Orders</h1>
    <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->user->email }}</td>
                    <td>{{ $order->items->first()?->product_name }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $orders->links() }}
</div>
@endsection
