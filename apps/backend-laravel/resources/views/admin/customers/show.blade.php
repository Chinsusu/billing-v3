@extends('layouts.admin', ['title' => 'Customer '.$customer->email])
@section('content')
@php
    $isActiveCustomerTab = fn (string $tab): bool => $activeCustomerTab === $tab;
@endphp

<x-page-header
    title="{{ $customer->name }}"
    subtitle="{{ $customer->email }}"
    eyebrow="Customers"
>
    <x-slot:actions>
        @can('customers.view')
            <form method="POST" action="/admin/customers/{{ $customer->id }}/impersonate" class="customer-detail-action-form">
                @csrf
                <button type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.31 0-6 1.79-6 4v1h12v-1c0-2.21-2.69-4-6-4Zm7-9h-3V3h5v5h-2V5Zm-14 8h2v2H2v-5h2v3Zm14 3v-3h2v5h-5v-2h3ZM5 5v3H3V3h5v2H5Z"/></svg>
                    <span>Impersonate</span>
                </button>
            </form>
        @endcan
        <a class="button secondary button-soft" href="/admin/customers">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Customers</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel customer-activity-panel" data-customer-activity-tabs data-active-customer-tab="{{ $activeCustomerTab }}">
    <div class="panel-heading">
        <div>
            <h2>Customer Workspace</h2>
        </div>
    </div>

    <div class="customer-activity-tabs__list" role="tablist" aria-label="Customer workspace sections">
        <button
            type="button"
            class="customer-activity-tab"
            id="customer-details-tab"
            role="tab"
            aria-selected="{{ $isActiveCustomerTab('details') ? 'true' : 'false' }}"
            aria-controls="customer-details-panel"
            data-customer-activity-tab="customer-details-panel"
            @if (! $isActiveCustomerTab('details')) tabindex="-1" @endif
        >
            <span>Customer Details</span>
        </button>
        <button
            type="button"
            class="customer-activity-tab"
            id="customer-ledger-tab"
            role="tab"
            aria-selected="{{ $isActiveCustomerTab('ledger') ? 'true' : 'false' }}"
            aria-controls="customer-ledger-panel"
            data-customer-activity-tab="customer-ledger-panel"
            @if (! $isActiveCustomerTab('ledger')) tabindex="-1" @endif
        >
            <span>Recent Ledger</span>
            <strong>{{ $ledgerEntries->total() }}</strong>
        </button>
        <button
            type="button"
            class="customer-activity-tab"
            id="customer-invoices-tab"
            role="tab"
            aria-selected="{{ $isActiveCustomerTab('invoices') ? 'true' : 'false' }}"
            aria-controls="customer-invoices-panel"
            data-customer-activity-tab="customer-invoices-panel"
            @if (! $isActiveCustomerTab('invoices')) tabindex="-1" @endif
        >
            <span>Invoices</span>
            <strong>{{ $invoices->total() }}</strong>
        </button>
        <button
            type="button"
            class="customer-activity-tab"
            id="customer-orders-tab"
            role="tab"
            aria-selected="{{ $isActiveCustomerTab('orders') ? 'true' : 'false' }}"
            aria-controls="customer-orders-panel"
            data-customer-activity-tab="customer-orders-panel"
            @if (! $isActiveCustomerTab('orders')) tabindex="-1" @endif
        >
            <span>Orders</span>
            <strong>{{ $orders->total() }}</strong>
        </button>
        <button
            type="button"
            class="customer-activity-tab"
            id="customer-services-tab"
            role="tab"
            aria-selected="{{ $isActiveCustomerTab('services') ? 'true' : 'false' }}"
            aria-controls="customer-services-panel"
            data-customer-activity-tab="customer-services-panel"
            @if (! $isActiveCustomerTab('services')) tabindex="-1" @endif
        >
            <span>Services</span>
            <strong>{{ $services->total() }}</strong>
        </button>
    </div>

    <section class="customer-activity-panel__body" id="customer-details-panel" role="tabpanel" aria-labelledby="customer-details-tab" @if (! $isActiveCustomerTab('details')) hidden @endif>
        <div class="customer-detail-grid customer-detail-grid--compact">
            <section class="customer-detail-section">
                <div class="customer-detail-section__heading">
                    <h4>Account owner</h4>
                </div>
                <div class="metric-list metric-list--compact">
                    <div class="metric-row">
                        <span>Email</span>
                        <strong>{{ $customer->email }}</strong>
                    </div>
                    <div class="metric-row">
                        <span>Reseller</span>
                        <strong>{{ $customer->reseller?->email ?? 'Direct customer' }}</strong>
                    </div>
                </div>
                <form method="POST" action="/admin/customers/{{ $customer->id }}/reseller" class="customer-detail-form">
                    @csrf
                    <label for="reseller_id">Assigned reseller</label>
                    <div class="customer-detail-form-row">
                        <select id="reseller_id" name="reseller_id">
                            <option value="">Direct customer</option>
                            @foreach ($resellers as $reseller)
                                <option value="{{ $reseller->id }}" @selected($customer->reseller_id === $reseller->id)>{{ $reseller->email }}</option>
                            @endforeach
                        </select>
                        <button type="submit">Save Reseller</button>
                    </div>
                </form>
            </section>

            <section class="customer-detail-section">
                <div class="customer-detail-section__heading">
                    <h4>Wallets</h4>
                </div>
                <div class="data-table data-table--compact">
                    <table>
                        <thead><tr><th>Currency</th><th>Balance</th></tr></thead>
                        <tbody>
                            @forelse ($wallets as $wallet)
                                <tr>
                                    <td>{{ $wallet->currency }}</td>
                                    <td><strong>{{ number_format($wallet->balance_amount) }} {{ $wallet->currency }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="muted">No wallets yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @can('wallets.adjust')
                <section class="customer-detail-section">
                    <div class="customer-detail-section__heading">
                        <h4>Manual Adjustment</h4>
                    </div>
                    <form method="POST" action="/admin/customers/{{ $customer->id }}/wallet-adjustments" class="customer-adjustment-form customer-adjustment-form--compact">
                        @csrf
                        <label>Direction
                            <select name="direction">
                                <option value="credit">credit</option>
                                <option value="debit">debit</option>
                            </select>
                        </label>
                        <label>Amount<input name="amount" type="number" min="1" required></label>
                        <label>Currency<input name="currency" value="VND" maxlength="3" required></label>
                        <label>Reference<input name="reference" placeholder="Optional idempotency reference"></label>
                        <label class="customer-adjustment-form__wide">Reason<textarea name="reason" rows="2" required></textarea></label>
                        <div class="customer-adjustment-form__actions">
                            <button type="submit">Record Adjustment</button>
                        </div>
                    </form>
                </section>
            @endcan
        </div>
    </section>

    <section class="customer-activity-panel__body" id="customer-ledger-panel" role="tabpanel" aria-labelledby="customer-ledger-tab" @if (! $isActiveCustomerTab('ledger')) hidden @endif>
        <div class="customer-activity-panel__heading">
            <h3>Recent Ledger</h3>
            <span class="muted">{{ number_format($ledgerEntries->total()) }} entries</span>
        </div>
        <div class="data-table">
            <table>
                <thead><tr><th>When</th><th>Direction</th><th>Amount</th><th>Balance</th><th>Description</th><th>Source</th></tr></thead>
                <tbody>
                    @forelse ($ledgerEntries as $entry)
                        <tr>
                            <td>{{ $entry->created_at?->toDateTimeString() }}</td>
                            <td><x-status-badge :tone="$entry->direction === 'credit' ? 'success' : 'warning'">{{ $entry->direction }}</x-status-badge></td>
                            <td>{{ number_format($entry->amount) }} {{ $entry->currency }}</td>
                            <td>{{ number_format($entry->balance_after) }} {{ $entry->currency }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>{{ $entry->source_type }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No ledger entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-activity-pagination :paginator="$ledgerEntries" name="ledger" />
    </section>

    <section class="customer-activity-panel__body" id="customer-invoices-panel" role="tabpanel" aria-labelledby="customer-invoices-tab" @if (! $isActiveCustomerTab('invoices')) hidden @endif>
        <div class="customer-activity-panel__heading">
            <h3>Invoices</h3>
            <span class="muted">{{ number_format($invoices->total()) }} invoices</span>
        </div>
        <div class="data-table">
            <table>
                <thead><tr><th>Invoice</th><th>Status</th><th>Total</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td>
                            <td><x-status-badge :tone="$invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'neutral' : 'warning')">{{ $invoice->status }}</x-status-badge></td>
                            <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                            <td>{{ $invoice->created_at?->toDateTimeString() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-activity-pagination :paginator="$invoices" name="invoices" />
    </section>

    <section class="customer-activity-panel__body" id="customer-orders-panel" role="tabpanel" aria-labelledby="customer-orders-tab" @if (! $isActiveCustomerTab('orders')) hidden @endif>
        <div class="customer-activity-panel__heading">
            <h3>Orders</h3>
            <span class="muted">{{ number_format($orders->total()) }} orders</span>
        </div>
        <div class="data-table">
            <table>
                <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="/admin/orders/{{ $order->id }}">{{ $order->order_number }}</a></td>
                            <td><x-status-badge :tone="$order->status === 'paid' ? 'success' : ($order->status === 'failed' ? 'danger' : 'warning')">{{ $order->status }}</x-status-badge></td>
                            <td>{{ number_format($order->total_amount) }} {{ $order->currency }}</td>
                            <td>{{ $order->created_at?->toDateTimeString() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-activity-pagination :paginator="$orders" name="orders" />
    </section>

    <section class="customer-activity-panel__body" id="customer-services-panel" role="tabpanel" aria-labelledby="customer-services-tab" @if (! $isActiveCustomerTab('services')) hidden @endif>
        <div class="customer-activity-panel__heading">
            <h3>Services</h3>
            <span class="muted">{{ number_format($services->total()) }} services</span>
        </div>
        <div class="data-table">
            <table>
                <thead><tr><th>Product</th><th>Status</th><th>Expires</th><th>Created</th></tr></thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td><a href="/admin/services/{{ $service->id }}">{{ $service->product_name }}</a></td>
                            <td><x-status-badge :tone="$service->status === 'active' ? 'success' : ($service->status === 'cancelled' ? 'neutral' : 'warning')">{{ $service->status }}</x-status-badge></td>
                            <td>{{ $service->expires_at?->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $service->created_at?->toDateTimeString() ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No services yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-activity-pagination :paginator="$services" name="services" />
    </section>
</div>

<script>
    document.querySelectorAll('[data-customer-activity-tabs]').forEach((container) => {
        const tabs = Array.from(container.querySelectorAll('[role="tab"]'));
        const panels = Array.from(container.querySelectorAll('[role="tabpanel"]'));

        const activate = (tab) => {
            tabs.forEach((candidate) => {
                const selected = candidate === tab;
                candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
                candidate.tabIndex = selected ? 0 : -1;
            });

            panels.forEach((panel) => {
                panel.hidden = panel.id !== tab.dataset.customerActivityTab;
            });
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab));
            tab.addEventListener('keydown', (event) => {
                const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
                if (!keys.includes(event.key)) {
                    return;
                }

                event.preventDefault();
                const nextIndex = {
                    ArrowLeft: index === 0 ? tabs.length - 1 : index - 1,
                    ArrowRight: index === tabs.length - 1 ? 0 : index + 1,
                    Home: 0,
                    End: tabs.length - 1,
                }[event.key];
                tabs[nextIndex].focus();
                activate(tabs[nextIndex]);
            });
        });

        const selected = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];
        if (selected) {
            activate(selected);
        }
    });
</script>
@endsection
