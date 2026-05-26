@extends('layouts.admin', ['title' => 'Provisioning Job'])
@section('content')
<div class="panel">
    <p><a href="/admin/provisioning-jobs">Back to Provisioning Jobs</a></p>
    <h1>Provisioning Job</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $provisioningJob->id }}</td></tr>
            <tr><th>Reference</th><td>{{ $provisioningJob->idempotency_key }}</td></tr>
            <tr><th>Type</th><td>{{ $provisioningJob->type }}</td></tr>
            <tr><th>Status</th><td>{{ $provisioningJob->status }}</td></tr>
            <tr><th>Attempts</th><td>{{ $provisioningJob->attempts }}</td></tr>
            <tr><th>User</th><td>{{ $provisioningJob->user?->email ?? '-' }}</td></tr>
            <tr><th>Service</th><td>{{ $provisioningJob->service?->product_code ?? '-' }}</td></tr>
            <tr><th>Available</th><td>{{ $provisioningJob->available_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Processed</th><td>{{ $provisioningJob->processed_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Error</th><td>{{ $provisioningJob->last_error ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Execution Logs</h2>
    <table>
        <thead>
            <tr>
                <th>Created</th>
                <th>Action</th>
                <th>Driver</th>
                <th>Endpoint</th>
                <th>Status</th>
                <th>HTTP</th>
                <th>Duration</th>
                <th>Error</th>
                <th>Provider Account</th>
                <th>Request</th>
                <th>Response</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($provisioningJob->executionLogs as $log)
                <tr>
                    <td>{{ $log->created_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->driver }}</td>
                    <td>{{ $log->endpoint ?? '-' }}</td>
                    <td>{{ $log->status }}</td>
                    <td>{{ $log->http_status ?? '-' }}</td>
                    <td>{{ $log->duration_ms }}ms</td>
                    <td>{{ $log->error_code ?? $log->error_message ?? '-' }}</td>
                    <td>{{ $log->providerAccount?->slug ?? '-' }}</td>
                    <td><pre>{{ json_encode($log->request_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                    <td><pre>{{ json_encode($log->response_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted">No execution logs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
