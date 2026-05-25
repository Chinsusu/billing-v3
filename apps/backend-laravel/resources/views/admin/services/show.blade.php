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
        <div><strong>Auto-renew</strong><br>{{ $service->auto_renew_enabled ? 'Enabled' : 'Disabled' }}</div>
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
    <h2>Auto-Renewal Attempts</h2>
    <table>
        <thead><tr><th>Status</th><th>Attempts</th><th>Target Expiry</th><th>Renewed Expiry</th><th>Next Retry</th><th>Amount</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->autoRenewalAttempts as $attempt)
                <tr>
                    <td>{{ $attempt->status }}</td>
                    <td>{{ $attempt->attempts }}</td>
                    <td>{{ $attempt->expires_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $attempt->renewed_expires_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $attempt->next_attempt_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $attempt->amount ? $attempt->amount.' '.$attempt->currency : '-' }}</td>
                    <td>{{ $attempt->last_error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No auto-renewal attempts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@can('wallets.adjust')
<div class="panel">
    <h2>Refund Credit</h2>
    <form method="POST" action="/admin/services/{{ $service->id }}/refund-credit">
        @csrf
        <label>Amount<input name="amount" type="number" min="1" required></label>
        <label>Currency<input name="currency" value="VND" maxlength="3" required></label>
        <label>Reason<textarea name="reason" rows="3" required></textarea></label>
        <label>Reference<input name="reference" placeholder="Optional idempotency reference"></label>
        <button type="submit">Credit Refund</button>
    </form>
</div>
@endcan

<div class="panel">
    <h2>Cancellation Requests</h2>
    <table>
        <thead><tr><th>Mode</th><th>Status</th><th>Requested</th><th>Completed</th><th>Reason</th><th>Provider Job</th></tr></thead>
        <tbody>
            @forelse ($service->cancellations as $cancellation)
                <tr>
                    <td>{{ $cancellation->mode }}</td>
                    <td>{{ $cancellation->status }}</td>
                    <td>{{ $cancellation->requested_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $cancellation->completed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $cancellation->reason ?? '-' }}</td>
                    <td>{{ $cancellation->provider_action_job_id ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No cancellation requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
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
    <h2>Provider Callbacks</h2>
    <table>
        <thead><tr><th>Event</th><th>Action</th><th>Status</th><th>Processing</th><th>Received</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->providerCallbackEvents as $event)
                <tr>
                    <td>{{ $event->provider_event_id ?? $event->id }}</td>
                    <td>{{ $event->action ?? '-' }}</td>
                    <td>{{ $event->provider_status ?? '-' }}</td>
                    <td>{{ $event->processing_status }}</td>
                    <td>{{ $event->processed_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $event->error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No provider callbacks yet.</td></tr>
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
