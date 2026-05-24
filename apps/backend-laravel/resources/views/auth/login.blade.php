@extends('layouts.app', ['title' => 'Login'])
@section('content')
<div class="panel">
    <h1>Login</h1>
    <form method="POST" action="/login">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Password<input type="password" name="password" required></label>
        <p><button type="submit">Login</button></p>
    </form>
</div>
@endsection
