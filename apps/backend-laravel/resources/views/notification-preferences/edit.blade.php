@extends('layouts.app', ['title' => 'Notification Preferences'])
@section('content')
<div class="panel">
    <h1>Notification Preferences</h1>
    <form method="POST" action="/notification-preferences">
        @csrf
        @foreach ($notificationTypes as $type => $name)
            <label>
                <input type="checkbox" name="enabled_types[]" value="{{ $type }}" @checked($states[$type] ?? true) style="width:auto">
                {{ $name }}
            </label>
        @endforeach
        <p><button type="submit">Save Preferences</button></p>
    </form>
</div>
@endsection
