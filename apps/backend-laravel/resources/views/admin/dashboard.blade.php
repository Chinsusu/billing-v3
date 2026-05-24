@extends('layouts.app', ['title' => 'Admin Dashboard'])
@section('content')
<div class="panel">
    <h1>Admin Dashboard</h1>
    <div class="grid">
        <div class="panel"><strong>{{ $productCount }}</strong><br>Products</div>
        <div class="panel"><strong>{{ $activeProductCount }}</strong><br>Active products</div>
        <div class="panel"><strong>{{ $openInvoiceCount }}</strong><br>Open invoices</div>
        <div class="panel"><strong>{{ $paymentEventCount }}</strong><br>Payment events</div>
    </div>
    <p>
        <a class="button" href="/admin/products">Manage Products</a>
        <a class="button secondary" href="/admin/invoices">Invoices</a>
        <a class="button secondary" href="/admin/payment-events">Payment Events</a>
    </p>
</div>
@endsection
