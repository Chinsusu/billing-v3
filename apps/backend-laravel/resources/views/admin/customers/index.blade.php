@extends('layouts.admin', ['title' => 'Admin Customers'])
@section('content')
<x-page-header
    title="Customers"
/>

<div class="panel invoice-filter-panel customer-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/customers">
        <div class="invoice-filter-fields admin-filter-fields--1">
            <label class="invoice-filter-field" for="search">
                <span>Search</span>
                <input id="search" name="search" value="{{ $search }}" placeholder="Search customer" list="admin-customer-search-options" autocomplete="off">
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Search</button>
        </div>
        <datalist id="admin-customer-search-options">
            @foreach ($customerOptions as $customerOption)
                <option value="{{ $customerOption['value'] }}" label="{{ $customerOption['label'] }}"></option>
            @endforeach
        </datalist>
    </form>
</div>

<div class="panel">
    @if ($customers->isEmpty())
        <x-empty-state title="No customers found." message="Try another search term or wait for customer registration." />
    @else
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Balance</th>
                        <th>Active</th>
                        <th>Suspended</th>
                        <th>Cancelled</th>
                        <th>Invoices</th>
                        <th>Orders</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        @php
                            $walletSummary = $customer->wallets->isEmpty()
                                ? '-'
                                : $customer->wallets
                                    ->map(fn ($wallet) => number_format($wallet->balance_amount).' '.$wallet->currency)
                                    ->implode(' / ');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $customer->name }}</strong><br>
                                <span class="muted">{{ $customer->email }}</span>
                            </td>
                            <td><strong>{{ $walletSummary }}</strong></td>
                            <td><x-status-badge tone="success">{{ $customer->active_services_count }} active</x-status-badge></td>
                            <td><x-status-badge tone="warning">{{ $customer->suspended_services_count }} suspended</x-status-badge></td>
                            <td><x-status-badge tone="neutral">{{ $customer->cancelled_services_count }} cancelled</x-status-badge></td>
                            <td>{{ $customer->invoices_count }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td><a class="button secondary" href="/admin/customers/{{ $customer->id }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
    @endif
</div>
@endsection
