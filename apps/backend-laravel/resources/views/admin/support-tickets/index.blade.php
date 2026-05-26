@extends('layouts.admin', ['title' => 'Admin Support Tickets'])
@section('content')
<div class="panel">
    <h1>Support Tickets</h1>
    <form method="GET" action="/admin/support-tickets">
        <label>Status
            <select name="status">
                <option value="">All</option>
                @foreach (['open', 'pending', 'resolved', 'closed'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <p><button type="submit">Filter</button></p>
    </form>
</div>
<div class="panel">
    <table>
        <thead><tr><th>Subject</th><th>Customer</th><th>Status</th><th>Priority</th><th>Assigned</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="/admin/support-tickets/{{ $ticket->id }}">{{ $ticket->subject }}</a></td>
                    <td>{{ $ticket->user?->email }}</td>
                    <td>{{ $ticket->status }}</td>
                    <td>{{ $ticket->priority }}</td>
                    <td>{{ $ticket->assignedTo?->email ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No support tickets.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $tickets->links() }}
</div>
@endsection
