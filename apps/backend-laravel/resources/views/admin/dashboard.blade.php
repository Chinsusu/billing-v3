@extends('layouts.admin', ['title' => 'Admin Dashboard'])
@section('content')
@php
    $money = fn (int $amount): string => number_format($amount).' '.$revenueCurrency;
    $queueRiskCount = $failedProvisioningJobCount + $stuckProvisioningJobCount;
@endphp

<x-page-header
    title="Admin Dashboard"
>
    <x-slot:actions>
        @can('provisioning_jobs.view')
            <a class="button" href="/admin/ops-health">Open Ops Health</a>
        @endcan
        @can('invoices.view')
            <a class="button secondary" href="/admin/reports/billing">Billing Reports</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="ops-dashboard-grid admin-kpi-grid admin-kpi-grid--single-row">
    <x-stat-card
        class="admin-kpi-card"
        data-kpi-card="revenue-mtd"
        label="Revenue MTD"
        :value="$money($monthlyRevenueAmount)"
        :meta="'Today '.$money($todayRevenueAmount).' / paid orders + invoices'"
        tone="success"
        icon="revenue"
        :href="auth()->user()->can('invoices.view') ? '/admin/reports/billing' : null"
    />
    <x-stat-card
        data-kpi-card="active-services"
        label="Active services"
        :value="$activeServiceCount"
        :meta="$activeServiceCount.' currently active'"
        icon="services"
        :href="auth()->user()->can('services.view') ? '/admin/services?status=active' : null"
    />
    <x-stat-card
        data-kpi-card="provisioning"
        label="Provisioning"
        :value="$pendingProvisioningServiceCount"
        :meta="$pendingProvisioningServiceCount.' waiting services / '.$pendingProvisioningJobCount.' queued jobs'"
        tone="warning"
        icon="provisioning"
        :href="auth()->user()->can('provisioning_jobs.view') ? '/admin/provisioning-jobs?status=pending' : null"
    />
    <x-stat-card
        data-kpi-card="queue-risk"
        label="Queue risk"
        :value="$queueRiskCount"
        :meta="$failedProvisioningJobCount.' failed / '.$stuckProvisioningJobCount.' stuck'"
        :tone="$queueRiskCount > 0 ? 'danger' : 'success'"
        icon="queue-risk"
        :href="auth()->user()->can('provisioning_jobs.view') ? '/admin/provisioning-jobs' : null"
    />
    <x-stat-card
        data-kpi-card="open-support-tickets"
        label="Open support tickets"
        :value="$openSupportTicketCount"
        :meta="$urgentSupportTicketCount.' urgent'"
        icon="support"
        :href="auth()->user()->can('support_tickets.view') ? '/admin/support-tickets' : null"
    />
</div>

<div class="admin-dashboard-grid admin-dashboard-grid--queue-only">
    <div class="panel admin-dashboard-panel admin-dashboard-panel--wide admin-dashboard-panel--queue-preview">
        <div class="panel-heading">
            <div>
                <h2>Provisioning queue</h2>
            </div>
            @can('provisioning_jobs.view')
                <a class="button secondary" href="/admin/provisioning-jobs">Review queue</a>
            @endcan
        </div>

        <div class="admin-dashboard-queue-scroll" data-dashboard-section="provisioning-queue-preview">
            @if ($provisioningQueueJobs->isEmpty())
                <x-empty-state title="No active provisioning work." message="Pending, processing, and failed provisioning jobs will appear here." />
            @else
                <div class="data-table admin-dashboard-table admin-dashboard-table--queue">
                    <table>
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Service</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Waiting</th>
                                <th>Last error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($provisioningQueueJobs as $job)
                                @php
                                    $statusTone = match ($job->status) {
                                        'failed' => 'danger',
                                        'processing' => 'warning',
                                        default => 'neutral',
                                    };
                                    $waitingSince = $job->available_at ?? $job->created_at;
                                @endphp
                                <tr>
                                    <td><a href="/admin/provisioning-jobs/{{ $job->id }}">{{ $job->type }}</a></td>
                                    <td>{{ $job->service?->product_name ?? '-' }}</td>
                                    <td>{{ $job->user?->email ?? '-' }}</td>
                                    <td><x-status-badge :tone="$statusTone">{{ $job->status }}</x-status-badge></td>
                                    <td>{{ $waitingSince?->diffForHumans() ?? '-' }}</td>
                                    <td>{{ $job->last_error ? \Illuminate\Support\Str::limit($job->last_error, 80) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="admin-dashboard-grid admin-dashboard-grid--secondary">
    <div class="panel admin-dashboard-panel">
        <div class="panel-heading">
            <div>
                <h2>Recent paid orders</h2>
            </div>
            @can('orders.view')
                <a class="button secondary" href="/admin/orders?status=paid">Open Orders</a>
            @endcan
        </div>

        @if ($recentPaidOrders->isEmpty())
            <x-empty-state title="No paid orders yet." message="Paid customer orders will appear here." />
        @else
            <div class="activity-list">
                @foreach ($recentPaidOrders as $order)
                    <div class="activity-item">
                        <div class="activity-main">
                            <a href="/admin/orders/{{ $order->id }}">{{ $order->order_number }}</a>
                            <span class="activity-meta">{{ $order->user?->email ?? '-' }} / {{ $order->paid_at?->diffForHumans() ?? '-' }}</span>
                        </div>
                        <div class="activity-amount">{{ $money((int) $order->total_amount) }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="panel admin-dashboard-panel">
        <div class="panel-heading">
            <div>
                <h2>Financial exceptions</h2>
            </div>
            @can('payment_events.view')
                <a class="button secondary" href="/admin/payment-events">Payment Events</a>
            @endcan
        </div>
        <div class="metric-list">
            <div class="metric-row">
                <span>Open invoices</span>
                <strong>{{ $openInvoiceCount }}</strong>
            </div>
            <div class="metric-row">
                <span>Open amount</span>
                <strong>{{ $money($openInvoiceAmount) }}</strong>
            </div>
            <div class="metric-row">
                <span>Payment exceptions</span>
                <strong>{{ $paymentExceptionCount }}</strong>
            </div>
            <div class="metric-row">
                <span>Total payment events</span>
                <strong>{{ $paymentEventCount }}</strong>
            </div>
        </div>
    </div>

    <div class="panel admin-dashboard-panel">
        <div class="panel-heading">
            <div>
                <h2>Platform inventory</h2>
            </div>
            @can('products.view')
                <a class="button secondary" href="/admin/products">Manage Products</a>
            @endcan
        </div>
        <div class="metric-list">
            <div class="metric-row">
                <span>Total customers</span>
                <strong>{{ $customerCount }}</strong>
            </div>
            <div class="metric-row">
                <span>Total orders</span>
                <strong>{{ $orderCount }}</strong>
            </div>
            <div class="metric-row">
                <span>Auto-renew enabled</span>
                <strong>{{ $autoRenewEnabledServiceCount }}</strong>
            </div>
            <div class="metric-row">
                <span>Renewal failures (7d)</span>
                <strong>{{ $failedAutoRenewAttemptCount }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <h2>Quick actions</h2>
    <div class="quick-action-grid">
        @can('bank_integrations.manage')
            <a class="quick-action" href="/admin/bank-integrations"><strong>Bank integrations</strong></a>
        @endcan
        @can('provisioning_provider_accounts.manage')
            <a class="quick-action" href="/admin/provisioning-provider-accounts"><strong>Provider accounts</strong></a>
        @endcan
        @can('provisioning_jobs.view')
            <a class="quick-action" href="/admin/provider-action-jobs"><strong>Provider actions</strong></a>
        @endcan
        @can('support_tickets.view')
            <a class="quick-action" href="/admin/support-tickets"><strong>Support tickets</strong></a>
        @endcan
        @can('notifications.manage')
            <a class="quick-action" href="/admin/notification-events"><strong>Notification Events</strong></a>
            <a class="quick-action" href="/admin/notification-templates"><strong>Notification Templates</strong></a>
        @endcan
        @can('audit_logs.view')
            <a class="quick-action" href="/admin/audit-logs"><strong>Audit logs</strong></a>
        @endcan
        @can('users.view')
            <a class="quick-action" href="/admin/users"><strong>Users &amp; roles</strong></a>
        @endcan
    </div>
</div>
@endsection
