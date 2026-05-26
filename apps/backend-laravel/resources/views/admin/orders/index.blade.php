@extends('layouts.admin', ['title' => 'Admin Orders'])
@section('content')
<div class="panel">
    <h1>Orders</h1>
    <form method="GET" action="/admin/orders">
        <div class="grid">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="customer">Customer email</label>
                <input id="customer" name="customer" value="{{ $filters['customer'] }}">
            </div>
        </div>
        <p>
            <button type="submit">Filter</button>
            <a class="button secondary" href="/admin/orders">Reset</a>
        </p>
    </form>
    <table>
        <thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="/admin/orders/{{ $order->id }}">{{ $order->order_number }}</a></td>
                    <td><a href="/admin/customers/{{ $order->user_id }}">{{ $order->user->email }}</a></td>
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
