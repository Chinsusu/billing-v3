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
                    <div class="product-form-field product-form-field--wide invoice-customer-picker" data-customer-picker>
                        <label for="invoice-customer-search">Customer email</label>
                        <input
                            id="invoice-customer-search"
                            type="search"
                            value="{{ old('user_email') }}"
                            placeholder="Search customers"
                            autocomplete="off"
                            aria-controls="invoice-customer-options"
                            aria-expanded="false"
                            data-customer-search
                            required
                        >
                        <input id="invoice-user-email" name="user_email" type="hidden" value="{{ old('user_email') }}" data-customer-value>

                        <div id="invoice-customer-options" class="invoice-customer-options" role="listbox" data-customer-options>
                            @forelse ($customers as $customer)
                                @php
                                    $customerSearch = strtolower($customer->email.' '.$customer->name);
                                    $selected = old('user_email') === $customer->email;
                                @endphp
                                <button
                                    type="button"
                                    class="invoice-customer-option"
                                    role="option"
                                    data-customer-option
                                    data-email="{{ $customer->email }}"
                                    data-search="{{ $customerSearch }}"
                                    aria-selected="{{ $selected ? 'true' : 'false' }}"
                                >
                                    <strong>{{ $customer->email }}</strong>
                                    <small>{{ $customer->name }}</small>
                                </button>
                            @empty
                                <div class="invoice-customer-empty">No customer accounts yet.</div>
                            @endforelse
                            <div class="invoice-customer-empty" data-customer-empty hidden>No matching customers.</div>
                        </div>
                    </div>
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-customer-picker]').forEach((picker) => {
            const search = picker.querySelector('[data-customer-search]');
            const value = picker.querySelector('[data-customer-value]');
            const optionPanel = picker.querySelector('[data-customer-options]');
            const emptyState = picker.querySelector('[data-customer-empty]');
            const options = Array.from(picker.querySelectorAll('[data-customer-option]'));
            const form = picker.closest('form');
            let suppressNextFocus = false;

            if (!search || !value || !optionPanel) {
                return;
            }

            const normalize = (text) => (text || '').trim().toLowerCase();

            const setOpen = (open) => {
                picker.classList.toggle('is-open', open);
                search.setAttribute('aria-expanded', open ? 'true' : 'false');
            };

            const visibleOptions = () => options.filter((option) => !option.hidden);

            const markSelected = (email) => {
                options.forEach((option) => {
                    option.setAttribute('aria-selected', option.dataset.email === email ? 'true' : 'false');
                });
            };

            const selectOption = (option) => {
                if (!option) {
                    return;
                }

                search.value = option.dataset.email;
                value.value = option.dataset.email;
                markSelected(option.dataset.email);
                suppressNextFocus = true;
                setOpen(false);
                search.focus();
            };

            const filterOptions = () => {
                const term = normalize(search.value);
                let shown = 0;
                let exactMatch = null;

                options.forEach((option) => {
                    const matches = term === '' || normalize(option.dataset.search).includes(term);
                    option.hidden = !matches;
                    if (matches) {
                        shown += 1;
                    }
                    if (normalize(option.dataset.email) === term) {
                        exactMatch = option;
                    }
                });

                if (emptyState) {
                    emptyState.hidden = shown !== 0;
                }

                if (exactMatch) {
                    value.value = exactMatch.dataset.email;
                    markSelected(exactMatch.dataset.email);
                } else if (normalize(value.value) !== term) {
                    value.value = '';
                    markSelected('');
                }

                setOpen(true);
            };

            options.forEach((option) => {
                option.addEventListener('click', () => selectOption(option));
            });

            search.addEventListener('focus', () => {
                if (suppressNextFocus) {
                    suppressNextFocus = false;

                    return;
                }

                filterOptions();
            });
            search.addEventListener('input', filterOptions);
            search.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                    return;
                }

                if (event.key === 'Enter' && picker.classList.contains('is-open')) {
                    const firstVisible = visibleOptions()[0];
                    if (firstVisible) {
                        event.preventDefault();
                        selectOption(firstVisible);
                    }
                }
            });

            form?.addEventListener('submit', () => {
                if (!value.value && search.value.trim() !== '') {
                    value.value = search.value.trim();
                }
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    setOpen(false);
                }
            });
        });
    });
</script>
@endsection
