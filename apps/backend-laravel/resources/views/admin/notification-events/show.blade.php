@extends('layouts.admin', ['title' => 'Notification Event'])
@section('content')
<div class="panel">
    <p><a href="/admin/notification-events">Back to Notification Events</a></p>
    <h1>Notification Event</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $notificationEvent->id }}</td></tr>
            <tr><th>Type</th><td>{{ $notificationEvent->type }}</td></tr>
            <tr><th>Channel</th><td>{{ $notificationEvent->channel }}</td></tr>
            <tr><th>Recipient</th><td>{{ $notificationEvent->recipient_email }}</td></tr>
            <tr><th>User</th><td>{{ $notificationEvent->user?->email ?? '-' }}</td></tr>
            <tr><th>Status</th><td>{{ $notificationEvent->status }}</td></tr>
            <tr><th>Attempts</th><td>{{ $notificationEvent->attempts }} / {{ $notificationEvent->max_attempts }}</td></tr>
            <tr><th>Available</th><td>{{ $notificationEvent->available_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Sent</th><td>{{ $notificationEvent->sent_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Source</th><td>{{ $notificationEvent->source_type }} {{ $notificationEvent->source_id ?? '' }}</td></tr>
            <tr><th>Idempotency</th><td>{{ $notificationEvent->idempotency_key }}</td></tr>
            <tr><th>Error</th><td>{{ $notificationEvent->last_error ?? '-' }}</td></tr>
        </tbody>
    </table>
    @if ($notificationEvent->status === 'failed')
        <form method="POST" action="/admin/notification-events/{{ $notificationEvent->id }}/retry">
            @csrf
            <button type="submit">Retry</button>
        </form>
    @endif
</div>

<div class="panel">
    <h2>Subject</h2>
    <p>{{ $notificationEvent->subject }}</p>
</div>

<div class="panel">
    <h2>Body</h2>
    <pre>{{ $notificationEvent->body_text }}</pre>
</div>

<div class="panel">
    <h2>Payload</h2>
    <pre>{{ json_encode($notificationEvent->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
