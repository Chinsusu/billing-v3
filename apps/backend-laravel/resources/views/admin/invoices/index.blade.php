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

@php
    $invoiceTableColumns = auth()->user()?->can('invoices.update') ? 5 : 4;
    $customerOptions = $customers
        ->map(fn ($customer) => [
            'value' => $customer->email,
            'label' => $customer->email,
            'meta' => $customer->name,
            'search' => $customer->email.' '.$customer->name,
        ])
        ->all();
@endphp

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
            <x-realtime-search
                id="invoice-filter-customer"
                name="customer"
                label="Customer email"
                placeholder="Search customer email"
                :value="$filters['customer']"
                :options="$customerOptions"
                empty-text="No matching customers."
                no-options-text="No customer accounts yet."
                class="invoice-filter-field invoice-filter-field--grow"
                :allow-free-text="true"
            />
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
                @can('invoices.update')
                    <th>Actions</th>
                @endcan
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td>
                    <td><a href="/admin/customers/{{ $invoice->user_id }}">{{ $invoice->user->email }}</a></td>
                    <td><x-status-badge :tone="$invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'neutral' : 'warning')">{{ $invoice->status }}</x-status-badge></td>
                    <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                    @can('invoices.update')
                        <td>
                            <a href="/admin/invoices/{{ $invoice->id }}/edit" class="button secondary button-soft button-compact">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83a.996.996 0 0 0 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29Zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75Z"/></svg>
                                <span>Edit</span>
                            </a>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="{{ $invoiceTableColumns }}" class="muted">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $invoices->links() }}
</div>
@endsection
