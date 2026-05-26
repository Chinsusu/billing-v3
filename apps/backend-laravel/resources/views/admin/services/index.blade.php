@extends('layouts.admin', ['title' => 'Admin Services'])
@section('content')
<div class="panel">
    <h1>Services</h1>
    <p>
        <a href="/admin/provider-action-jobs">Provider Action Jobs</a>
        @can('renewals.view')
            | <a href="/admin/renewals">Renewal Reporting</a>
        @endcan
    </p>
    <table>
        <thead><tr><th>Customer</th><th>Product</th><th>Type</th><th>Status</th><th>Expires</th><th>Auto-renew</th><th>Latest renewal</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($services as $service)
                @php($latestAutoRenewalAttempt = $service->latestAutoRenewalAttemptForCurrentExpiry())
                <tr>
                    <td>{{ $service->user->email }}</td>
                    <td><a href="/admin/services/{{ $service->id }}">{{ $service->product_name }}</a></td>
                    <td>{{ $service->product_type }}</td>
                    <td>{{ $service->status }}</td>
                    <td>{{ $service->expires_at?->format('Y-m-d') }}</td>
                    <td>{{ $service->auto_renew_enabled ? 'Enabled' : 'Disabled' }}</td>
                    <td>{{ $latestAutoRenewalAttempt?->status ?? '-' }}</td>
                    <td>
                        <form method="POST" action="/admin/services/{{ $service->id }}/sync-provider" style="display:inline">
                            @csrf
                            <button class="button secondary" type="submit">Sync Provider</button>
                        </form>
                        <form method="POST" action="/admin/services/{{ $service->id }}/cancel-provider" style="display:inline">
                            @csrf
                            <button class="button secondary" type="submit">Cancel Provider</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No services yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $services->links() }}
</div>
@endsection
