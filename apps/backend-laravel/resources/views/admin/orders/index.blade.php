@extends('layouts.admin', ['title' => 'Admin Orders'])
@section('content')
<x-page-header
    title="Orders"
    subtitle="Review customer orders, payment status, and provisioning outcomes."
    eyebrow="Resources"
>
    @can('orders.create')
        <x-slot:actions>
            <a href="/admin/orders/create" class="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z"/></svg>
                <span>Create Order</span>
            </a>
        </x-slot:actions>
    @endcan
</x-page-header>

<div class="panel">
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
