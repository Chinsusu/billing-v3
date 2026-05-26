@extends('layouts.admin', ['title' => 'Admin Dashboard'])
@section('content')
<x-page-header
    title="Admin Dashboard"
    subtitle="Operational overview for billing, provisioning, renewals, and customer support."
>
    <x-slot:actions>
        <a class="button" href="/admin/ops-health">Open Ops Health</a>
        <a class="button secondary" href="/admin/customers">Customer Base</a>
    </x-slot:actions>
</x-page-header>

<div class="ops-dashboard-grid">
    <x-stat-card label="Open invoices" :value="$openInvoiceCount" href="/admin/invoices" />
    <x-stat-card label="Pending jobs" :value="$pendingProvisioningJobCount" tone="warning" href="/admin/provisioning-jobs" />
    @can('renewals.view')
        <x-stat-card label="Renewal failures" :value="$failedAutoRenewAttemptCount" tone="danger" href="/admin/renewals" />
    @else
        <x-stat-card label="Renewal failures" :value="$failedAutoRenewAttemptCount" tone="danger" />
    @endcan
    <x-stat-card label="Payment events" :value="$paymentEventCount" href="/admin/payment-events" />
    <x-stat-card label="Orders" :value="$orderCount" href="/admin/orders" />
    <x-stat-card label="Services" :value="$serviceCount" href="/admin/services" />
</div>

<div class="panel">
    <div class="page-header" style="margin-bottom: 0;">
        <div>
            <h2>Platform inventory</h2>
            <p class="page-subtitle">{{ $activeProductCount }} active of {{ $productCount }} products. {{ $autoRenewEnabledServiceCount }} services have auto-renew enabled.</p>
        </div>
        <div class="page-header-actions">
            <a class="button secondary" href="/admin/products">Manage Products</a>
            <a class="button secondary" href="/admin/services">Review Services</a>
        </div>
    </div>
</div>

<div class="panel">
    <h2>Quick actions</h2>
    <div class="quick-action-grid">
        <a class="quick-action" href="/admin/bank-integrations"><strong>Bank integrations</strong><span>Configure private bank endpoints and tests.</span></a>
        <a class="quick-action" href="/admin/provisioning-provider-accounts"><strong>Provider accounts</strong><span>Manage endpoint/API key account routing.</span></a>
        <a class="quick-action" href="/admin/provider-action-jobs"><strong>Provider actions</strong><span>Retry sync, cancellation, and recovery jobs.</span></a>
        <a class="quick-action" href="/admin/support-tickets"><strong>Support tickets</strong><span>Review customer support workflow.</span></a>
        @can('notifications.manage')
            <a class="quick-action" href="/admin/notification-events"><strong>Notification Events</strong><span>Inspect delivery and retry failures.</span></a>
            <a class="quick-action" href="/admin/notification-templates"><strong>Notification Templates</strong><span>Edit notification copy and channels.</span></a>
        @endcan
        @can('audit_logs.view')
            <a class="quick-action" href="/admin/audit-logs"><strong>Audit logs</strong><span>Trace operator changes and security events.</span></a>
        @endcan
        @can('users.view')
            <a class="quick-action" href="/admin/users"><strong>Users &amp; roles</strong><span>Manage operators and permissions.</span></a>
        @endcan
    </div>
</div>
@endsection
