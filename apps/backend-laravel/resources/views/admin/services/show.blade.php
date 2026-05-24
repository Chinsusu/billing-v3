@extends('layouts.app', ['title' => 'Service Runbook'])
@section('content')
<div class="panel">
    <p><a href="/admin/services">Back to Services</a></p>
    <h1>Service Runbook</h1>
    <div class="grid">
        <div><strong>Customer</strong><br>{{ $service->user?->email ?? '-' }}</div>
        <div><strong>Product</strong><br>{{ $service->product_name }}</div>
        <div><strong>Code</strong><br>{{ $service->product_code }}</div>
        <div><strong>Type</strong><br>{{ $service->product_type }}</div>
        <div><strong>Status</strong><br>{{ $service->status }}</div>
        <div><strong>External ID</strong><br>{{ $service->external_id ?? '-' }}</div>
        <div><strong>Order</strong><br>{{ $service->order?->order_number ?? '-' }}</div>
        <div><strong>Provisioned</strong><br>{{ $service->provisioned_at?->toDateTimeString() ?? '-' }}</div>
        <div><strong>Expires</strong><br>{{ $service->expires_at?->toDateTimeString() ?? '-' }}</div>
    </div>
    <p>
        <form method="POST" action="/admin/services/{{ $service->id }}/sync-provider" style="display:inline">
            @csrf
            <button class="button secondary" type="submit">Sync Provider</button>
        </form>
        <form method="POST" action="/admin/services/{{ $service->id }}/cancel-provider" style="display:inline">
            @csrf
            <button class="button secondary" type="submit">Cancel Provider</button>
        </form>
    </p>
</div>

<div class="panel">
    <h2>Provisioning Jobs</h2>
    <table>
        <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->provisioningJobs as $job)
                <tr>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->type }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No provisioning jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Provider Action Jobs</h2>
    <table>
        <thead><tr><th>Reference</th><th>Action</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->providerActionJobs as $job)
                <tr>
                    <td>{{ $job->idempotency_key }}</td>
                    <td>{{ $job->action }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}/{{ $job->max_attempts }}</td>
                    <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No provider action jobs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Execution Logs</h2>
    <table>
        <thead><tr><th>Created</th><th>Action</th><th>Driver</th><th>Endpoint</th><th>Status</th><th>HTTP</th><th>Duration</th><th>Error</th><th>Request</th><th>Response</th></tr></thead>
        <tbody>
            @forelse ($service->provisioningExecutionLogs as $log)
                <tr>
                    <td>{{ $log->created_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->driver }}</td>
                    <td>{{ $log->endpoint ?? '-' }}</td>
                    <td>{{ $log->status }}</td>
                    <td>{{ $log->http_status ?? '-' }}</td>
                    <td>{{ $log->duration_ms }}ms</td>
                    <td>{{ $log->error_code ?? $log->error_message ?? '-' }}</td>
                    <td><pre>{{ json_encode($log->request_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                    <td><pre>{{ json_encode($log->response_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">No execution logs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
