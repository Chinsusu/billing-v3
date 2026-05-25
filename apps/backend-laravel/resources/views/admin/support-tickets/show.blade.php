@extends('layouts.app', ['title' => 'Admin Support Ticket'])
@section('content')
<div class="panel">
    <h1>{{ $ticket->subject }}</h1>
    <p>{{ $ticket->user?->email }} | {{ $ticket->status }} | {{ $ticket->priority }}</p>
    <form method="POST" action="/admin/support-tickets/{{ $ticket->id }}">
        @csrf
        @method('PUT')
        <div class="grid">
            <label>Status
                <select name="status">
                    @foreach (['open', 'pending', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected($ticket->status === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label>Priority
                <select name="priority">
                    @foreach (['low', 'normal', 'high', 'urgent'] as $priority)
                        <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
            </label>
            <label>Assigned
                <select name="assigned_to_id">
                    <option value="">Unassigned</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected($ticket->assigned_to_id === $operator->id)>{{ $operator->email }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p><button type="submit">Update Ticket</button></p>
    </form>
</div>
<div class="panel">
    <h2>Notes</h2>
    @foreach ($ticket->notes as $note)
        <div class="panel">
            <p>{{ $note->body }}</p>
            <p class="muted">{{ $note->visibility }} | {{ $note->author?->email ?? 'system' }} | {{ $note->created_at?->toDateTimeString() }}</p>
        </div>
    @endforeach
    <form method="POST" action="/admin/support-tickets/{{ $ticket->id }}/notes">
        @csrf
        <label>Visibility
            <select name="visibility">
                <option value="internal">internal</option>
                <option value="customer">customer</option>
            </select>
        </label>
        <label>Note<textarea name="body" rows="4" required></textarea></label>
        <p><button type="submit">Add Note</button></p>
    </form>
</div>
@endsection
