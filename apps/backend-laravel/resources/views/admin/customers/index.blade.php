@extends('layouts.app', ['title' => 'Admin Customers'])
@section('content')
<div class="panel">
    <h1>Customers</h1>
    <form method="GET" action="/admin/customers">
        <label for="search">Search</label>
        <input id="search" name="search" value="{{ $search }}" placeholder="Email or name">
        <button type="submit">Search</button>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Invoices</th><th>Orders</th><th>Services</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->email }}</td>
                    <td>{{ $customer->invoices_count }}</td>
                    <td>{{ $customer->orders_count }}</td>
                    <td>{{ $customer->services_count }}</td>
                    <td><a href="/admin/customers/{{ $customer->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No customers found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $customers->links() }}
</div>
@endsection
