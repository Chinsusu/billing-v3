@extends('layouts.app', ['title' => 'Customer Dashboard'])
@section('content')
<div class="panel">
    <h1>Customer Dashboard</h1>
    <p class="muted">Wallet funding and invoice payment are available in Sprint 2.</p>
    <p>
        <a class="button" href="/wallet">Open Wallet</a>
        <a class="button secondary" href="/services">Services</a>
    </p>
</div>
@endsection
