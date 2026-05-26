@extends('layouts.app', ['title' => 'Reseller Customers'])

@section('content')
<div class="panel">
    <h1>Reseller Customers</h1>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Services</th></tr></thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->email }}</td>
                    <td>{{ $customer->orders_count }}</td>
                    <td>{{ $customer->services_count }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No assigned customers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $customers->links() }}
</div>
@endsection
