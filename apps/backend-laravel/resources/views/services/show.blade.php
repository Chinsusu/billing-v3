@extends('layouts.app', ['title' => 'Service'])
@section('content')
<div class="panel">
    <p><a href="/services">Back to services</a></p>
    <h1>{{ $service->product_name }}</h1>
    <div class="grid">
        <div><strong>Status</strong><br>{{ $service->status }}</div>
        <div><strong>Product</strong><br>{{ $service->product_code }}</div>
        <div><strong>Type</strong><br>{{ $service->product_type }}</div>
        <div><strong>External ID</strong><br>{{ $service->external_id ?? '-' }}</div>
        <div><strong>Provisioned</strong><br>{{ $service->provisioned_at?->format('Y-m-d H:i') ?? '-' }}</div>
        <div><strong>Expires</strong><br>{{ $service->expires_at?->format('Y-m-d H:i') ?? '-' }}</div>
    </div>
</div>

<div class="panel">
    <h2>Provisioning History</h2>
    <table>
        <thead><tr><th>Type</th><th>Status</th><th>Attempts</th><th>Processed</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->provisioningJobs as $job)
                <tr>
                    <td>{{ $job->type }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->processed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No provisioning jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
