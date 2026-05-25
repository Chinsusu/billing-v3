@extends('layouts.app', ['title' => 'Support Ticket'])
@section('content')
<div class="panel">
    <h1>{{ $ticket->subject }}</h1>
    <p>Status: {{ $ticket->status }} | Priority: {{ $ticket->priority }}</p>
    <p><a href="/support/tickets">Back to Tickets</a></p>
</div>
<div class="panel">
    <h2>Messages</h2>
    @foreach ($ticket->notes as $note)
        <div class="panel">
            <p>{{ $note->body }}</p>
            <p class="muted">{{ $note->author?->email ?? 'system' }} - {{ $note->created_at?->toDateTimeString() }}</p>
        </div>
    @endforeach
    <form method="POST" action="/support/tickets/{{ $ticket->id }}/notes">
        @csrf
        <label>Reply<textarea name="body" rows="4" required></textarea></label>
        <p><button type="submit">Add Reply</button></p>
    </form>
</div>
@endsection
