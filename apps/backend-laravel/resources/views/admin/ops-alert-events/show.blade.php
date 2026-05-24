@extends('layouts.app', ['title' => 'Ops Alert Event'])
@section('content')
<div class="panel">
    <p><a href="/admin/ops-alert-events">Back to Ops Alert Events</a></p>
    <h1>Ops Alert Event</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $event->id }}</td></tr>
            <tr><th>Status</th><td>{{ $event->status }}</td></tr>
            <tr><th>Severity</th><td>{{ $event->severity }}</td></tr>
            <tr><th>Title</th><td>{{ $event->title }}</td></tr>
            <tr><th>Message</th><td>{{ $event->message }}</td></tr>
            <tr><th>Fingerprint</th><td>{{ $event->fingerprint }}</td></tr>
            <tr><th>Rule</th><td>{{ $event->rule?->name ?? '-' }}</td></tr>
            <tr><th>Rule Type</th><td>{{ $event->rule?->type ?? '-' }}</td></tr>
            <tr><th>Rule Severity</th><td>{{ $event->rule?->severity ?? '-' }}</td></tr>
            <tr><th>Rule Cooldown</th><td>{{ $event->rule?->cooldown_minutes ?? '-' }} minutes</td></tr>
            <tr><th>First Seen</th><td>{{ $event->first_seen_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Last Seen</th><td>{{ $event->last_seen_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Acknowledged</th><td>{{ $event->acknowledged_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Acknowledged By</th><td>{{ $event->acknowledgedBy?->email ?? '-' }}</td></tr>
            <tr><th>Resolved</th><td>{{ $event->resolved_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Resolved By</th><td>{{ $event->resolvedBy?->email ?? '-' }}</td></tr>
            <tr><th>Delivery</th><td>{{ $event->delivery_status ?? '-' }}</td></tr>
            <tr><th>Delivery Error</th><td>{{ $event->delivery_error ?? '-' }}</td></tr>
        </tbody>
    </table>
    <p>
        @if ($event->status === 'open')
            <form method="POST" action="/admin/ops-alert-events/{{ $event->id }}/acknowledge" style="display:inline">
                @csrf
                <button type="submit">Acknowledge</button>
            </form>
        @endif
        @if ($event->status !== 'resolved')
            <form method="POST" action="/admin/ops-alert-events/{{ $event->id }}/resolve" style="display:inline">
                @csrf
                <button class="button secondary" type="submit">Resolve</button>
            </form>
        @endif
    </p>
</div>

<div class="panel">
    <h2>Context</h2>
    <pre>{{ json_encode($event->context ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
