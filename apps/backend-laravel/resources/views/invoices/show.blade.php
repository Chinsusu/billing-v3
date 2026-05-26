@extends('layouts.app', ['title' => 'Invoice '.$invoice->invoice_number])
@section('content')
<div class="panel">
    <h1>{{ $invoice->invoice_number }}</h1>
    <p>Status: <strong>{{ $invoice->status }}</strong></p>
    <p>Total: <strong>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</strong></p>
    @if ($invoice->description)
        <p>{{ $invoice->description }}</p>
    @endif
    @if ($invoice->status === 'open')
        <form method="POST" action="/invoices/{{ $invoice->id }}/pay">
            @csrf
            <button type="submit">Pay from wallet</button>
        </form>
    @endif
</div>
@endsection
