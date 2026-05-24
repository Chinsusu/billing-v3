@extends('layouts.app', ['title' => 'Admin Services'])
@section('content')
<div class="panel">
    <h1>Services</h1>
    <table>
        <thead><tr><th>Customer</th><th>Product</th><th>Type</th><th>Status</th><th>Expires</th></tr></thead>
        <tbody>
            @forelse ($services as $service)
                <tr>
                    <td>{{ $service->user->email }}</td>
                    <td>{{ $service->product_name }}</td>
                    <td>{{ $service->product_type }}</td>
                    <td>{{ $service->status }}</td>
                    <td>{{ $service->expires_at?->format('Y-m-d') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No services yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $services->links() }}
</div>
@endsection
