@extends('layouts.app', ['title' => 'Wallet Top-up'])
@section('content')
<div class="panel">
    <h1>Wallet Top-up</h1>
    <p><strong>{{ number_format($paymentIntent->amount) }} {{ $paymentIntent->currency }}</strong></p>
    <p>Reference: <strong>{{ $paymentIntent->reference }}</strong></p>
    <p>Status: {{ $paymentIntent->status }}</p>
    <label for="qr_payload">QR payload</label>
    <textarea id="qr_payload" rows="4" readonly>{{ $paymentIntent->qr_payload }}</textarea>
    <p class="muted">Use this reference with the private bank API sandbox webhook.</p>
    <p><a class="button secondary" href="/wallet">Back to wallet</a></p>
</div>
@endsection
