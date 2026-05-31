@extends('layouts.admin', ['title' => 'Notification Events'])
@section('content')
<x-page-header title="Notification Events" eyebrow="Security" />

<div class="panel invoice-filter-panel notification-event-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/notification-events">
        <div class="invoice-filter-fields admin-filter-fields--3">
            <label class="invoice-filter-field" for="notification-status">
                <span>Status</span>
                <input id="notification-status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Search status" list="notification-event-status-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field" for="notification-type">
                <span>Type</span>
                <input id="notification-type" name="type" value="{{ $filters['type'] ?? '' }}" placeholder="Search type" list="notification-event-type-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field" for="notification-recipient">
                <span>Recipient</span>
                <input id="notification-recipient" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="Search recipient" list="notification-event-recipient-options" autocomplete="off">
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary button-soft" href="/admin/notification-events">Clear</a>
        </div>
        <datalist id="notification-event-status-options">
            @foreach ($filterOptions['statuses'] as $status)
                <option value="{{ $status }}"></option>
            @endforeach
        </datalist>
        <datalist id="notification-event-type-options">
            @foreach ($filterOptions['types'] as $type)
                <option value="{{ $type }}"></option>
            @endforeach
        </datalist>
        <datalist id="notification-event-recipient-options">
            @foreach ($filterOptions['recipients'] as $recipient)
                <option value="{{ $recipient }}"></option>
            @endforeach
        </datalist>
    </form>
</div>

<div class="panel">
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
