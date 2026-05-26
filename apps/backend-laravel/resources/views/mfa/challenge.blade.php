@extends('layouts.app', ['title' => 'MFA Challenge'])
@section('content')
<div class="panel">
    <h1>MFA Challenge</h1>
    <form method="POST" action="/mfa/challenge">
        @csrf
        <label>Authenticator Code
            <input name="code" inputmode="numeric" required>
        </label>
        <p><button type="submit">Verify</button></p>
    </form>
</div>
<div class="panel">
    <h2>Recovery Code</h2>
    <form method="POST" action="/mfa/recovery">
        @csrf
        <label>Recovery Code
            <input name="recovery_code">
        </label>
        <p><button type="submit">Use Recovery Code</button></p>
    </form>
</div>
@endsection
