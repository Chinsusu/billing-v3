@extends('layouts.admin', ['title' => 'Edit Transaction'])
@section('content')
<x-page-header
    title="Edit Transaction"
    eyebrow="Financials"
>
    <x-slot:actions>
        <a href="/admin/payment-events/{{ $event->id }}" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Transaction</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell" method="POST" action="/admin/payment-events/{{ $event->id }}">
        @csrf
        @method('PUT')

        <div class="product-form-grid">
            <section class="product-form-section" aria-labelledby="transaction-edit-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">01</span>
                    <div>
                        <h2 class="product-form-section-title" id="transaction-edit-title">Manual transaction</h2>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field product-form-field--wide" for="provider-transaction-id">
                        <span>Transaction ID</span>
                        <input id="provider-transaction-id" name="provider_transaction_id" value="{{ old('provider_transaction_id', $event->provider_transaction_id) }}">
                    </label>
                    <label class="product-form-field product-form-field--wide" for="payment-reference">
                        <span>Reference</span>
                        <input id="payment-reference" name="reference" value="{{ old('reference', $event->reference) }}">
                    </label>
                    <label class="product-form-field" for="processed-at">
                        <span>Processed at</span>
                        <input id="processed-at" name="processed_at" type="datetime-local" value="{{ old('processed_at', $event->processed_at?->format('Y-m-d\TH:i')) }}">
                    </label>
                </div>
            </section>

            <section class="product-form-section" aria-labelledby="transaction-summary-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">02</span>
                    <div>
                        <h2 class="product-form-section-title" id="transaction-summary-title">Read-only accounting</h2>
                    </div>
                </div>

                <div class="product-form-fields">
                    <div class="product-form-field">
                        <span>Status</span>
                        <input value="{{ $event->status }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Amount</span>
                        <input value="{{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Customer</span>
                        <input value="{{ $customer?->email ?? '-' }}" readonly>
                    </div>
                    <div class="product-form-field">
                        <span>Invoice</span>
                        <input value="{{ $event->invoice?->invoice_number ?? '-' }}" readonly>
                    </div>
                </div>
            </section>
        </div>

        <div class="product-form-actions">
            <a href="/admin/payment-events/{{ $event->id }}" class="button secondary button-soft">Cancel</a>
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
                <span>Save Transaction</span>
            </button>
        </div>
    </form>
</div>
@endsection
