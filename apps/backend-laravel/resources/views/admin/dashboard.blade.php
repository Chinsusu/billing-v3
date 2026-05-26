@extends('layouts.admin', ['title' => 'Admin Dashboard'])
@section('content')
<div class="panel">
    <h1>Admin Dashboard</h1>
    <div class="grid">
        <div class="panel"><strong>{{ $productCount }}</strong><br>Products</div>
        <div class="panel"><strong>{{ $activeProductCount }}</strong><br>Active products</div>
        <div class="panel"><strong>{{ $openInvoiceCount }}</strong><br>Open invoices</div>
        <div class="panel"><strong>{{ $paymentEventCount }}</strong><br>Payment events</div>
        <div class="panel"><strong>{{ $orderCount }}</strong><br>Orders</div>
        <div class="panel"><strong>{{ $serviceCount }}</strong><br>Services</div>
        <div class="panel"><strong>{{ $autoRenewEnabledServiceCount }}</strong><br>Auto-renew enabled</div>
        <div class="panel"><strong>{{ $failedAutoRenewAttemptCount }}</strong><br>Renewal failures</div>
        <div class="panel"><strong>{{ $pendingProvisioningJobCount }}</strong><br>Pending jobs</div>
    </div>
    <p>
        <a class="button" href="/admin/products">Manage Products</a>
        <a class="button secondary" href="/admin/invoices">Invoices</a>
        <a class="button secondary" href="/admin/payment-events">Payment Events</a>
        <a class="button secondary" href="/admin/customers">Customers</a>
        <a class="button secondary" href="/admin/bank-integrations">Bank Integrations</a>
        <a class="button secondary" href="/admin/provisioning-provider-accounts">Provider Accounts</a>
        <a class="button secondary" href="/admin/orders">Orders</a>
        <a class="button secondary" href="/admin/services">Services</a>
        @can('renewals.view')
            <a class="button secondary" href="/admin/renewals">Renewal Reporting</a>
        @endcan
        <a class="button secondary" href="/admin/provisioning-jobs">Provisioning Jobs</a>
        @can('notifications.manage')
            <a class="button secondary" href="/admin/notification-events">Notification Events</a>
            <a class="button secondary" href="/admin/notification-templates">Notification Templates</a>
        @endcan
        <a class="button secondary" href="/admin/ops-health">Ops Health</a>
        <a class="button secondary" href="/admin/ops-alert-events">Ops Alerts</a>
        <a class="button secondary" href="/admin/scheduled-task-runs">Scheduled Task Runs</a>
        @can('audit_logs.view')
            <a class="button secondary" href="/admin/audit-logs">Audit Logs</a>
        @endcan
        @can('users.view')
            <a class="button secondary" href="/admin/users">Users &amp; Roles</a>
        @endcan
    </p>
</div>
@endsection
