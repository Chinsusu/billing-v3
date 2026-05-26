@extends('layouts.admin', ['title' => 'Notification Events'])
@section('content')
<div class="panel">
    <h1>Notification Events</h1>
    <form method="GET" action="/admin/notification-events">
        <label>Status<input name="status" value="{{ $filters['status'] ?? '' }}"></label>
        <label>Type<input name="type" value="{{ $filters['type'] ?? '' }}"></label>
        <label>Recipient<input name="recipient" value="{{ $filters['recipient'] ?? '' }}"></label>
        <button type="submit">Filter</button>
        <a href="/admin/notification-events">Clear</a>
    </form>
    <table>
        <thead><tr><th>Type</th><th>Recipient</th><th>Status</th><th>Attempts</th><th>Available</th><th>Sent</th><th>Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($notificationEvents as $event)
                <tr>
                    <td><a href="/admin/notification-events/{{ $event->id }}">{{ $event->type }}</a></td>
                    <td>{{ $event->recipient_email }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->attempts }} / {{ $event->max_attempts }}</td>
                    <td>{{ $event->available_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $event->sent_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $event->last_error ?? '-' }}</td>
                    <td>
                        @if ($event->status === 'failed')
                            <form method="POST" action="/admin/notification-events/{{ $event->id }}/retry">
                                @csrf
                                <button type="submit">Retry</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No notification events yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $notificationEvents->links() }}
</div>
@endsection
