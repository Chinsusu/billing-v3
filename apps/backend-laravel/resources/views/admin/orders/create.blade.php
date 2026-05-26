@extends('layouts.admin', ['title' => 'Create Order'])
@section('content')
<x-page-header
    title="Create Order"
    subtitle="Wallet will be debited immediately and provisioning will be queued after payment."
    eyebrow="Resources"
>
    <x-slot:actions>
        <a href="/admin/orders" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Orders</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell" method="POST" action="/admin/orders">
        @csrf

        <div class="product-form-grid">
            <section class="product-form-section" aria-labelledby="order-customer-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">01</span>
                    <div>
                        <h2 class="product-form-section-title" id="order-customer-title">Customer</h2>
                        <p class="product-form-section-copy">Choose the customer wallet that will be charged.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field product-form-field--wide" for="order-customer-id">
                        <span>Customer Account</span>
                        <select id="order-customer-id" name="customer_id" required>
                            <option value="">Select customer</option>
                            @foreach ($customers as $customer)
                                @php
                                    $walletSummary = $customer->wallets->isEmpty()
                                        ? 'No wallet'
                                        : $customer->wallets
                                            ->map(fn ($wallet) => number_format($wallet->balance_amount).' '.$wallet->currency)
                                            ->implode(' / ');
                                @endphp
                                <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                    {{ $customer->email }} - {{ $walletSummary }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="product-form-section" aria-labelledby="order-product-title">
                <div class="product-form-section-header">
                    <span class="product-form-section-index">02</span>
                    <div>
                        <h2 class="product-form-section-title" id="order-product-title">Product</h2>
                        <p class="product-form-section-copy">Only active products can be ordered for a customer.</p>
                    </div>
                </div>

                <div class="product-form-fields">
                    <label class="product-form-field product-form-field--wide" for="order-product-id">
                        <span>Product</span>
                        <select id="order-product-id" name="product_id" required>
                            <option value="">Select product</option>
                            @foreach ($products as $product)
                                @php
                                    $durationLabel = $product->lifecycle_unit === 'calendar_month'
                                        ? $product->lifecycle_count.' calendar month'.($product->lifecycle_count === 1 ? '' : 's')
                                        : $product->duration_days.' day'.($product->duration_days === 1 ? '' : 's');
                                @endphp
                                <option value="{{ $product->id }}" @selected(old('product_id') === $product->id)>
                                    {{ $product->name }} - {{ number_format($product->price_amount) }} {{ $product->currency }} - {{ $durationLabel }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>
        </div>

        <div class="product-form-actions">
            <a href="/admin/orders" class="button secondary button-soft">Cancel</a>
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 16.17-4.17-4.18-1.42 1.42L9 19 21 7l-1.42-1.41L9 16.17Z"/></svg>
                <span>Create Paid Order</span>
            </button>
        </div>
    </form>
</div>
@endsection
