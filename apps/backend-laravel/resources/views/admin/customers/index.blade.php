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
                <thead><tr><th>Name</th><th>Email</th><th>Invoices</th><th>Orders</th><th>Services</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->invoices_count }}</td>
                            <td>{{ $customer->orders_count }}</td>
                            <td>{{ $customer->services_count }}</td>
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
