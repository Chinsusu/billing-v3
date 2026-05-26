@extends('layouts.admin', ['title' => 'Edit Notification Template'])
@section('content')
<div class="panel">
    <h1>Edit Notification Template</h1>
    <p class="muted">{{ $notificationTemplate->type }} / {{ $notificationTemplate->channel }}</p>
    <form method="POST" action="/admin/notification-templates/{{ $notificationTemplate->id }}">
        @csrf
        @method('PUT')
        <label>Name
            <input name="name" value="{{ old('name', $notificationTemplate->name) }}" required maxlength="120">
        </label>
        <label>
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $notificationTemplate->enabled)) style="width:auto">
            Enabled
        </label>
        <label>Subject template
            <input name="subject_template" value="{{ old('subject_template', $notificationTemplate->subject_template) }}" required maxlength="255">
        </label>
        <label>Body template
            <textarea name="body_template" rows="8" required>{{ old('body_template', $notificationTemplate->body_template) }}</textarea>
        </label>
        <p class="muted">Variables: {{ implode(', ', $notificationTemplate->variables ?? []) }}</p>
        <button type="submit">Save</button>
        <a href="/admin/notification-templates">Cancel</a>
    </form>
</div>
@endsection
