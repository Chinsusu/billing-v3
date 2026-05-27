@extends('layouts.admin', ['title' => 'Admin Customers'])
@section('content')
<x-page-header
    title="Customers"
    subtitle="Search customer accounts, inspect billing state, and open account workbenches."
/>

<div class="panel">
    <form method="GET" action="/admin/customers">
        <div class="filter-bar">
            <div>
                <label for="search">Search</label>
                <input id="search" name="search" value="{{ $search }}" placeholder="Email or name">
            </div>
            <button type="submit">Search</button>
        </div>
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
