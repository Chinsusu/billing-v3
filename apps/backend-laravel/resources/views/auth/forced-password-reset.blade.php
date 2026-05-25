@extends('layouts.app', ['title' => 'Change Password'])
@section('content')
<div class="panel">
    <h1>Change password</h1>
    <form method="POST" action="/password/forced-reset">
        @csrf
        <label>New Password
            <input type="password" name="password" required>
        </label>
        <label>Confirm Password
            <input type="password" name="password_confirmation" required>
        </label>
        <p><button type="submit">Change Password</button></p>
    </form>
</div>
@endsection
