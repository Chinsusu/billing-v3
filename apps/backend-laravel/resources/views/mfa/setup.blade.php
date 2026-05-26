@extends('layouts.app', ['title' => 'MFA Setup'])
@section('content')
<div class="panel">
    <h1>MFA Setup</h1>
    <p><strong>Authenticator Secret</strong></p>
    <p>{{ $secret }}</p>
    <p class="muted">{{ $provisioningUri }}</p>
    <form method="POST" action="/mfa/enable">
        @csrf
        <label>Authenticator Code
            <input name="code" inputmode="numeric" required>
        </label>
        <p><button type="submit">Enable MFA</button></p>
    </form>
</div>
@endsection
