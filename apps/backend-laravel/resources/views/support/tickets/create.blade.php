@extends('layouts.app', ['title' => 'Open Support Ticket'])
@section('content')
<div class="panel">
    <h1>Open Support Ticket</h1>
    <form method="POST" action="/support/tickets">
        @csrf
        <label>Subject<input name="subject" value="{{ old('subject') }}" required></label>
        <label>Priority
            <select name="priority">
                @foreach (['low', 'normal', 'high', 'urgent'] as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
        </label>
        <label>Message<textarea name="body" rows="5" required>{{ old('body') }}</textarea></label>
        <p><button type="submit">Open Ticket</button></p>
    </form>
</div>
@endsection
