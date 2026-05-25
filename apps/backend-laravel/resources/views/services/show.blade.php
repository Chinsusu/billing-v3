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
        <div><strong>Auto-renew</strong><br>{{ $service->auto_renew_enabled ? 'Enabled' : 'Disabled' }}</div>
    </div>
    @if ($service->status === 'active')
        <form method="POST" action="/services/{{ $service->id }}/auto-renew" style="margin-top:16px">
            @csrf
            <input type="hidden" name="enabled" value="{{ $service->auto_renew_enabled ? '0' : '1' }}">
            <button class="button secondary" type="submit">{{ $service->auto_renew_enabled ? 'Disable Auto-renew' : 'Enable Auto-renew' }}</button>
        </form>
        <form method="POST" action="/services/{{ $service->id }}/renew" style="margin-top:16px">
            @csrf
            <button type="submit">Renew</button>
        </form>
        <form method="POST" action="/services/{{ $service->id }}/cancel" style="margin-top:16px">
            @csrf
            <label>Cancellation mode
                <select name="mode">
                    <option value="period_end">period_end</option>
                    <option value="immediate">immediate</option>
                </select>
            </label>
            <label>Reason<textarea name="reason" rows="3"></textarea></label>
            <button class="button danger" type="submit">Request Cancellation</button>
        </form>
    @endif
</div>

<div class="panel">
    <h2>Auto-Renewal</h2>
    @php($latestAutoRenewalAttempt = $service->autoRenewalAttempts->first())
    @if ($latestAutoRenewalAttempt)
        <div class="grid">
            <div><strong>Status</strong><br>{{ $latestAutoRenewalAttempt->status }}</div>
            <div><strong>Attempts</strong><br>{{ $latestAutoRenewalAttempt->attempts }}</div>
            <div><strong>Target Expiry</strong><br>{{ $latestAutoRenewalAttempt->expires_at?->format('Y-m-d H:i') ?? '-' }}</div>
            <div><strong>Next Retry</strong><br>{{ $latestAutoRenewalAttempt->next_attempt_at?->format('Y-m-d H:i') ?? '-' }}</div>
        </div>
        @if ($latestAutoRenewalAttempt->status === 'failed')
            <p class="error">Auto-renew failed. We will retry automatically.</p>
        @endif
    @else
        <p class="muted">No auto-renewal attempts yet.</p>
    @endif
</div>

<div class="panel">
    <h2>Cancellation History</h2>
    <table>
        <thead><tr><th>Mode</th><th>Status</th><th>Requested</th><th>Completed</th><th>Reason</th></tr></thead>
        <tbody>
            @forelse ($service->cancellations as $cancellation)
                <tr>
                    <td>{{ $cancellation->mode }}</td>
                    <td>{{ $cancellation->status }}</td>
                    <td>{{ $cancellation->requested_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $cancellation->completed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $cancellation->reason ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No cancellation requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Provider Action History</h2>
    <table>
        <thead><tr><th>Action</th><th>Status</th><th>Attempts</th><th>Processed</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->providerActionJobs as $job)
                <tr>
                    <td>{{ $job->action }}</td>
                    <td>{{ $job->status }}</td>
                    <td>{{ $job->attempts }}</td>
                    <td>{{ $job->processed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $job->last_error ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No provider actions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Provider Execution Summary</h2>
    <table>
        <thead><tr><th>Action</th><th>Status</th><th>HTTP</th><th>Duration</th><th>Error</th></tr></thead>
        <tbody>
            @forelse ($service->provisioningExecutionLogs as $log)
                <tr>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->status }}</td>
                    <td>{{ $log->http_status ?? '-' }}</td>
                    <td>{{ $log->duration_ms }}ms</td>
                    <td>{{ $log->error_code ?? $log->error_message ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No provider execution logs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
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
