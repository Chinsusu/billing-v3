@extends('layouts.app', ['title' => 'Support Tickets'])
@section('content')
<div class="panel">
    <h1>Support Tickets</h1>
    <p><a class="button" href="/support/tickets/create">Open Ticket</a></p>
</div>
<div class="panel">
    <table>
        <thead><tr><th>Subject</th><th>Status</th><th>Priority</th><th>Updated</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="/support/tickets/{{ $ticket->id }}">{{ $ticket->subject }}</a></td>
                    <td>{{ $ticket->status }}</td>
                    <td>{{ $ticket->priority }}</td>
                    <td>{{ $ticket->last_activity_at?->toDateTimeString() ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No support tickets.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $tickets->links() }}
</div>
@endsection
