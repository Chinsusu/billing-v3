@extends('layouts.app', ['title' => 'Set Password'])
@section('content')
<div class="panel">
    <h1>Set password</h1>
    <form method="POST" action="/password/setup">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email
            <input type="email" name="email" value="{{ old('email', $email) }}" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label>Confirm Password
            <input type="password" name="password_confirmation" required>
        </label>
        <p><button type="submit">Set Password</button></p>
    </form>
</div>
@endsection
