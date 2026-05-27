@extends('layouts.admin', ['title' => 'Admin Invoices'])
@section('content')
<x-page-header
    title="Invoices"
    subtitle="Review invoice status, customer balances, and payment outcomes."
    eyebrow="Financials"
>
    @can('invoices.create')
        <x-slot:actions>
            <a href="/admin/invoices/create" class="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z"/></svg>
                <span>Create Invoice</span>
            </a>
        </x-slot:actions>
    @endcan
</x-page-header>

<div class="panel invoice-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/invoices">
        <div class="invoice-filter-fields">
            <label class="invoice-filter-field" for="status">
                <span>Status</span>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="invoice-filter-field invoice-filter-field--grow" for="customer">
                <span>Customer email</span>
                <input id="customer" name="customer" value="{{ $filters['customer'] }}" placeholder="customer@example.test">
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary" href="/admin/invoices">Reset</a>
        </div>
    </form>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td>
                    <td><a href="/admin/customers/{{ $invoice->user_id }}">{{ $invoice->user->email }}</a></td>
                    <td>{{ $invoice->status }}</td>
                    <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $invoices->links() }}
</div>
@endsection
