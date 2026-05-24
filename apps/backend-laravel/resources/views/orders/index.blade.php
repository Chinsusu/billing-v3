@extends('layouts.app', ['title' => 'Orders'])
@section('content')
<div class="panel">
    <h1>Orders</h1>
    <table>
        <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Paid</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td>
                    <td>{{ $order->paid_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td><a href="/orders/{{ $order->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $orders->links() }}
</div>
@endsection
