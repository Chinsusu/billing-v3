@extends('layouts.app', ['title' => 'Customer Dashboard'])
@section('content')
<x-page-header
    title="Customer Dashboard"
    subtitle="Track wallet balance, invoices, services, and renewal activity from one place."
>
    <x-slot:actions>
        <a class="button" href="/wallet">Open Wallet</a>
        <a class="button secondary" href="/wallet/top-ups">Top up</a>
    </x-slot:actions>
</x-page-header>

<div class="stat-grid">
    <x-stat-card label="Wallet balance" :value="number_format($wallet->balance_amount).' '.$wallet->currency" href="/wallet" />
    <x-stat-card label="Open invoices" :value="$openInvoiceCount" href="/invoices" />
    <x-stat-card label="Active services" :value="$activeServiceCount" href="/services" />
    <x-stat-card label="Auto-renew enabled" :value="$autoRenewEnabledCount" href="/services" />
    <x-stat-card label="Due for auto-renew" :value="$dueForAutoRenewCount" tone="warning" href="/services" />
    <x-stat-card label="Failed auto-renew" :value="$failedAutoRenewCount" tone="danger" href="/services" />
</div>

<div class="panel">
    <h2>Quick actions</h2>
    <div class="quick-action-grid">
        <a class="quick-action" href="/products"><strong>Browse products</strong><span>Order a new proxy, VPS, or service.</span></a>
        <a class="quick-action" href="/invoices"><strong>Review invoices</strong><span>Check unpaid and paid billing documents.</span></a>
        <a class="quick-action" href="/orders"><strong>Order history</strong><span>Track checkout and provisioning state.</span></a>
        <a class="quick-action" href="/notification-preferences"><strong>Notifications</strong><span>Choose the events you want to receive.</span></a>
    </div>
</div>

<div class="recent-grid">
    <div class="panel">
        <h2>Recent Invoices</h2>
        @if ($recentInvoices->isEmpty())
            <x-empty-state title="No invoices yet." message="New invoices will appear here after an order or renewal." />
        @else
            <div class="activity-list">
                @foreach ($recentInvoices as $invoice)
                    <div class="activity-item">
                        <div class="activity-main">
                            <a href="/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a>
                            <x-status-badge tone="neutral">{{ $invoice->status }}</x-status-badge>
                        </div>
                        <div class="activity-amount">{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="panel">
        <h2>Recent Orders</h2>
        @if ($recentOrders->isEmpty())
            <x-empty-state title="No orders yet." message="Orders will show here once checkout starts." />
        @else
            <div class="activity-list">
                @foreach ($recentOrders as $order)
                    <div class="activity-item">
                        <div class="activity-main">
                            <a href="/orders/{{ $order->id }}">{{ $order->order_number }}</a>
                            <x-status-badge tone="neutral">{{ $order->status }}</x-status-badge>
                        </div>
                        <div class="activity-amount">{{ number_format($order->total_amount) }} {{ $order->currency }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="panel">
        <h2>Recent Services</h2>
        @if ($recentServices->isEmpty())
            <x-empty-state title="No services yet." message="Active services and renewals will appear here." />
        @else
            <div class="activity-list">
                @foreach ($recentServices as $service)
                    @php($latestAutoRenewalAttempt = $service->latestAutoRenewalAttemptForCurrentExpiry())
                    <div class="activity-item">
                        <div class="activity-main">
                            <a href="/services/{{ $service->id }}">{{ $service->product_name }}</a>
                            <x-status-badge tone="neutral">{{ $service->status }}</x-status-badge>
                        </div>
                        <div class="activity-meta">{{ $latestAutoRenewalAttempt?->status ?? 'No renewal attempt' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="panel">
        <h2>Recent Top-ups</h2>
        @if ($recentTopUps->isEmpty())
            <x-empty-state title="No top-ups yet." message="Wallet funding attempts will appear here." />
        @else
            <div class="activity-list">
                @foreach ($recentTopUps as $topUp)
                    <div class="activity-item">
                        <div class="activity-main">
                            <a href="/wallet/top-ups/{{ $topUp->id }}">{{ $topUp->reference }}</a>
                            <x-status-badge tone="neutral">{{ $topUp->status }}</x-status-badge>
                        </div>
                        <div class="activity-amount">{{ number_format($topUp->amount) }} {{ $topUp->currency }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
