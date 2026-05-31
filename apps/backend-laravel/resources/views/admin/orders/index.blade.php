@extends('layouts.admin', ['title' => 'Admin Orders'])
@section('content')
<x-page-header
    title="Orders"
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

<div class="panel invoice-filter-panel order-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/orders">
        <div class="invoice-filter-fields admin-filter-fields--2">
            <label class="invoice-filter-field" for="status">
                <span>Status</span>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="invoice-filter-field" for="customer">
                <span>Customer</span>
                <input id="customer" name="customer" value="{{ $filters['customer'] }}" placeholder="Search customer" list="order-customer-options" autocomplete="off">
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary button-soft" href="/admin/orders">Reset</a>
        </div>
        <datalist id="order-customer-options">
            @foreach ($customerOptions as $customerEmail)
                <option value="{{ $customerEmail }}"></option>
            @endforeach
        </datalist>
    </form>
</div>

<div class="panel">
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
