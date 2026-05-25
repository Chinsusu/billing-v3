@extends('layouts.app', ['title' => 'Renewal Reporting'])
@section('content')
<div class="panel">
    <p><a href="/admin">Back to Admin</a></p>
    <h1>Renewal Reporting</h1>
    <div class="grid">
        <div class="panel"><strong>{{ $autoRenewEnabledCount }}</strong><br>Auto-renew enabled</div>
        <div class="panel"><strong>{{ $dueWithinPolicyCount }}</strong><br>Due within policy</div>
        <div class="panel"><strong>{{ $failedAttemptsLast7Days }}</strong><br>Failed attempts</div>
        <div class="panel"><strong>{{ $exhaustedAttemptCount }}</strong><br>Exhausted attempts</div>
    </div>
</div>

<div class="panel">
    <h2>Filters</h2>
    <form method="GET" action="/admin/renewals">
        <div class="grid">
            <label>Status
                <select name="status">
                    <option value="">Any status</option>
                    @foreach (['processing', 'succeeded', 'failed', 'skipped'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label>Product
                <select name="product_id">
                    <option value="">Any product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($filters['product_id'] === $product->id)>{{ $product->name }} ({{ $product->code }})</option>
                    @endforeach
                </select>
            </label>
            <label>Customer Email
                <input name="customer" value="{{ $filters['customer'] }}" placeholder="customer@example.test">
            </label>
        </div>
        <p>
            <button type="submit">Apply Filters</button>
            <a class="button secondary" href="/admin/renewals">Clear</a>
        </p>
    </form>
</div>

<div class="panel">
    <h2>Bulk Renewal Actions</h2>
    <form id="renewal-bulk-form" method="POST" action="/admin/renewals/bulk">
        @csrf
        <div class="grid">
            <label>Action
                <select name="action" required>
                    <option value="retry">Retry Now</option>
                    <option value="disable">Disable Auto-renew</option>
                </select>
            </label>
            <label>Reason
                <textarea name="reason" rows="2" required placeholder="Required audit reason"></textarea>
            </label>
        </div>
        <p><button type="submit">Apply Bulk Action</button></p>
    </form>
</div>

<div class="panel">
    <h2>Recent Attempts</h2>
    <table>
        <thead>
            <tr>
                <th>Select</th>
                <th>Service</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Target Expiry</th>
                <th>Next Retry</th>
                <th>Policy</th>
                <th>Error</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attempts as $attempt)
                <tr>
                    <td>
                        <input form="renewal-bulk-form" type="checkbox" name="attempt_ids[]" value="{{ $attempt->id }}">
                    </td>
                    <td>
                        @if ($attempt->service)
                            <a href="/admin/services/{{ $attempt->service_id }}">{{ $attempt->service->product_name }}</a>
                        @else
                            {{ $attempt->service_id }}
                        @endif
                    </td>
                    <td>{{ $attempt->service?->user?->email ?? $attempt->user?->email ?? '-' }}</td>
                    <td>{{ $attempt->status }}@if ($attempt->is_exhausted) <br><span class="error">Exhausted</span>@endif</td>
                    <td>{{ $attempt->attempts }} / {{ $attempt->renewal_policy['max_attempts'] }}</td>
                    <td>{{ $attempt->expires_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $attempt->next_attempt_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $attempt->renewal_policy['allowed'] ? 'Allowed' : 'Disabled' }}<br>{{ $attempt->renewal_policy['window_hours'] }}h window, {{ $attempt->renewal_policy['retry_delay_minutes'] }}m retry</td>
                    <td>{{ $attempt->last_error ?? '-' }}</td>
                    <td>
                        @if ($attempt->status === 'failed')
                            <form method="POST" action="/admin/renewals/{{ $attempt->id }}/retry">
                                @csrf
                                <label>Reason
                                    <input name="reason" required placeholder="Audit reason">
                                </label>
                                <button class="button secondary" type="submit">Retry Now</button>
                            </form>

                            @if ($attempt->is_exhausted)
                                <form method="POST" action="/admin/renewals/{{ $attempt->id }}/reset">
                                    @csrf
                                    <label>Reason
                                        <input name="reason" required placeholder="Audit reason">
                                    </label>
                                    <button class="button secondary" type="submit">Reset Attempts</button>
                                </form>
                            @endif
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">No auto-renewal attempts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $attempts->links() }}
</div>
@endsection
