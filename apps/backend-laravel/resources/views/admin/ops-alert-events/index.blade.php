@extends('layouts.app', ['title' => 'Ops Alert Events'])
@section('content')
<div class="panel">
    <h1>Ops Alert Events</h1>
    <p><a class="button secondary" href="/admin/ops-alert-rules">Ops Alert Rules</a></p>
    <table>
        <thead><tr><th>Status</th><th>Severity</th><th>Title</th><th>Message</th><th>Rule</th><th>Last Seen</th><th>Delivery</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->severity }}</td>
                    <td><a href="/admin/ops-alert-events/{{ $event->id }}">{{ $event->title }}</a></td>
                    <td>{{ $event->message }}</td>
                    <td>{{ $event->rule?->name ?? '-' }}</td>
                    <td>{{ $event->last_seen_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $event->delivery_status ?? '-' }} {{ $event->delivery_error ? '('.$event->delivery_error.')' : '' }}</td>
                    <td>
                        <a class="button secondary" href="/admin/ops-alert-events/{{ $event->id }}">View</a>
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
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No ops alert events yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $events->links() }}
</div>
@endsection
