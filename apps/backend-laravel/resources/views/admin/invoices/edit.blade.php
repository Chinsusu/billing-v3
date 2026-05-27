@extends('layouts.admin', ['title' => 'Edit Invoice '.$invoice->invoice_number])
@section('content')
<x-page-header
    title="Edit Invoice"
    subtitle="Update invoice status without changing wallet ledger or payment history."
    eyebrow="Financials"
>
    <x-slot:actions>
        <a href="/admin/invoices/{{ $invoice->id }}" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Invoice</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell" method="POST" action="/admin/invoices/{{ $invoice->id }}">
        @csrf
        @method('PUT')

        <div class="product-form-grid">
            <section class="product-form-section" aria-labelledby="invoice-status-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">01</span>
                    <div>
                        <h2 class="product-form-section-title" id="invoice-status-title">Invoice status</h2>
                        <p class="product-form-section-copy">Mark an invoice as open, paid, or void.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field product-form-field--wide" for="invoice-status">
                        <span>Status</span>
                        <select id="invoice-status" name="status" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $invoice->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="product-form-section" aria-labelledby="invoice-summary-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">02</span>
                    <div>
                        <h2 class="product-form-section-title" id="invoice-summary-title">Invoice summary</h2>
                        <p class="product-form-section-copy">Read-only billing context before saving.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <div class="product-form-field">
                        <span>Invoice</span>
                        <input value="{{ $invoice->invoice_number }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Customer</span>
                        <input value="{{ $invoice->user->email }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Total</span>
                        <input value="{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Paid at</span>
                        <input value="{{ $invoice->paid_at?->toDateTimeString() ?? '-' }}" readonly>
                    </div>
                </div>
            </section>
        </div>

        <div class="product-form-actions">
            <a href="/admin/invoices/{{ $invoice->id }}" class="button secondary button-soft">Cancel</a>
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
                <span>Save Status</span>
            </button>
        </div>
    </form>
</div>
@endsection
