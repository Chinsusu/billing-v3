@extends('layouts.app', ['title' => 'Billing Reports'])

@section('content')
<div class="panel">
    <h1>Billing Reports</h1>
    <div class="grid">
        <div>
            <h2>Invoices</h2>
            <p>{{ number_format($invoiceCount) }} records</p>
            <a class="button" href="/admin/reports/billing/invoices.csv">Export CSV</a>
        </div>
        <div>
            <h2>Ledger</h2>
            <p>{{ number_format($ledgerCount) }} records</p>
            <a class="button" href="/admin/reports/billing/ledger.csv">Export CSV</a>
        </div>
        <div>
            <h2>Payment Events</h2>
            <p>{{ number_format($paymentEventCount) }} records</p>
            <a class="button" href="/admin/reports/billing/payment-events.csv">Export CSV</a>
        </div>
    </div>
</div>
@endsection
