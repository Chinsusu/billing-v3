@extends('layouts.app', ['title' => 'Register'])
@section('content')
<div class="panel">
    <h1>Register</h1>
    <form method="POST" action="/register">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Password<input type="password" name="password" required></label>
        <label>Confirm Password<input type="password" name="password_confirmation" required></label>
        <p><button type="submit">Create account</button></p>
    </form>
</div>
@endsection
