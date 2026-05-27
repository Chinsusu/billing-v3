@extends('layouts.admin', ['title' => 'Create Invoice'])
@section('content')
<x-page-header
    title="Create Invoice"
    subtitle="Create an open invoice for a customer to pay from wallet balance."
    eyebrow="Financials"
>
    <x-slot:actions>
        <a href="/admin/invoices" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Invoices</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell" method="POST" action="/admin/invoices">
        @csrf

        <div class="product-form-grid">
            <section class="product-form-section" aria-labelledby="invoice-customer-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">01</span>
                    <div>
                        <h2 class="product-form-section-title" id="invoice-customer-title">Customer</h2>
                        <p class="product-form-section-copy">Select the account that will own this invoice.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field product-form-field--wide" for="invoice-user-email">
                        <span>Customer email</span>
                        <input id="invoice-user-email" name="user_email" type="email" value="{{ old('user_email') }}" list="invoice-customer-emails" required>
                        <datalist id="invoice-customer-emails">
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->email }}">{{ $customer->email }}</option>
                            @endforeach
                        </datalist>
                    </label>
                </div>
            </section>

            <section class="product-form-section" aria-labelledby="invoice-billing-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">02</span>
                    <div>
                        <h2 class="product-form-section-title" id="invoice-billing-title">Billing</h2>
                        <p class="product-form-section-copy">Set the payable amount and invoice line description.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field" for="invoice-total-amount">
                        <span>Total amount</span>
                        <input id="invoice-total-amount" name="total_amount" type="number" value="{{ old('total_amount') }}" min="1" inputmode="numeric" required>
                    </label>

                    <label class="product-form-field" for="invoice-currency">
                        <span>Currency</span>
                        <input id="invoice-currency" name="currency" value="{{ old('currency', 'VND') }}" maxlength="3" readonly required>
                    </label>

                    <label class="product-form-field product-form-field--wide" for="invoice-description">
                        <span>Description</span>
                        <textarea id="invoice-description" name="description" rows="5" required>{{ old('description') }}</textarea>
                    </label>
                </div>
            </section>
        </div>

        <div class="product-form-actions">
            <a href="/admin/invoices" class="button secondary button-soft">Cancel</a>
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
                <span>Create Invoice</span>
            </button>
        </div>
    </form>
</div>
@endsection
