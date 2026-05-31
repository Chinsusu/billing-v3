@extends('layouts.admin', ['title' => 'Service Detail'])
@section('content')
@php
    $config = $service->config ?? [];
    $provider = $service->meta['provider'] ?? [];
    $route = collect($provider['routes'] ?? [])->first();
    $host = $config['hostname'] ?? $config['host'] ?? $config['ip'] ?? $config['server_ip'] ?? $service->external_id ?? '-';
    $httpPort = collect(['port_http', 'http_port', 'port_https', 'https_port'])->map(fn ($key) => $config[$key] ?? null)->first(fn ($value) => $value !== null && $value !== '');
    $socksPort = collect(['port_socks', 'socks_port'])->map(fn ($key) => $config[$key] ?? null)->first(fn ($value) => $value !== null && $value !== '');
    $location = $config['location'] ?? $config['region'] ?? $config['group_name'] ?? ($route['billing_group_id'] ?? null) ?? '-';
    $statusTone = match ($service->status) {
        'active' => 'success',
        'pending_provision', 'processing', 'pending' => 'warning',
        'cancelled', 'expired', 'failed' => 'danger',
        default => 'neutral',
    };
    $orderLedger = $ledgerEntries->firstWhere('source_type', 'order');
@endphp

<style>
    .service-detail-hero {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1.4fr) minmax(360px, 0.8fr);
        margin-bottom: 16px;
    }

    .service-access-grid,
    .service-context-grid,
    .service-metric-grid {
        display: grid;
        gap: 12px;
    }

    .service-access-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .service-context-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .service-metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .service-field {
        background: var(--surface-soft);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        min-width: 0;
        padding: 12px;
    }

    .service-field__label {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .service-field__value {
        color: var(--text-heading);
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .service-detail-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 16px;
    }

    .service-detail-tabs a {
        align-items: center;
        background: var(--surface);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        color: var(--text-heading);
        display: inline-flex;
        font-weight: 700;
        min-height: 36px;
        padding: 8px 12px;
        text-decoration: none;
    }

    .service-detail-tabs a:hover {
        background: rgba(var(--primary-rgb), 0.08);
        color: rgb(var(--primary));
    }

    .service-section-title {
        align-items: center;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .service-section-title h2 {
        margin: 0;
    }

    .service-json {
        max-height: 280px;
        overflow: auto;
        white-space: pre-wrap;
    }

    @media (max-width: 1200px) {
        .service-detail-hero,
        .service-access-grid,
        .service-context-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<x-page-header
    title="Service Detail"
    eyebrow="Resources"
>
    <x-slot:actions>
        <form method="POST" action="/admin/services/{{ $service->id }}/sync-provider">
            @csrf
            <button class="button secondary" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6V3L8 7l4 4V8c2.8 0 5 2.2 5 5 0 .9-.2 1.7-.6 2.4l1.5 1.5c.7-1.1 1.1-2.4 1.1-3.9 0-3.9-3.1-7-7-7Zm-5.9.1C5.4 7.2 5 8.5 5 10c0 3.9 3.1 7 7 7v3l4-4-4-4v3c-2.8 0-5-2.2-5-5 0-.9.2-1.7.6-2.4L6.1 6.1Z"/></svg>
                <span>Sync Provider</span>
            </button>
        </form>
        <form method="POST" action="/admin/services/{{ $service->id }}/cancel-provider">
            @csrf
            <button class="button secondary" type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.71 12 12l6.3 6.29-1.41 1.41L10.59 13.41 4.29 19.71 2.88 18.3 9.17 12 2.88 5.71 4.29 4.29l6.3 6.3 6.29-6.3 1.42 1.42Z"/></svg>
                <span>Cancel Provider</span>
            </button>
        </form>
        <a href="/admin/services" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="service-detail-tabs" aria-label="Service detail sections">
    <a href="#access">Access</a>
    <a href="#billing">Billing</a>
    <a href="#provider">Provider</a>
    <a href="#jobs">Jobs</a>
    <a href="#logs">Logs</a>
</div>

<div class="service-detail-hero">
    <div class="panel" id="access">
        <div class="service-section-title">
            <h2>{{ $service->product_name }}</h2>
            <x-status-badge :tone="$statusTone">{{ $service->status }}</x-status-badge>
        </div>

        <div class="service-access-grid">
            <div class="service-field">
                <div class="service-field__label">Hostname/IP</div>
                <div class="service-field__value">{{ $host }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">User</div>
                <div class="service-field__value">{{ $config['username'] ?? '-' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Password</div>
                <div class="service-field__value">{{ $config['password'] ?? '-' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Ports</div>
                <div class="service-field__value">HTTP {{ $httpPort ?? '-' }} / SOCKS {{ $socksPort ?? '-' }}</div>
            </div>
        </div>

        <div class="service-context-grid" style="margin-top: 12px;">
            <div class="service-field">
                <div class="service-field__label">External ID</div>
                <div class="service-field__value">{{ $service->external_id ?? '-' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Location</div>
                <div class="service-field__value">{{ $location }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Connection URI</div>
                <div class="service-field__value">{{ $config['connection_uri'] ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="service-section-title">
            <h2>Lifecycle</h2>
            <x-status-badge :tone="$service->auto_renew_enabled ? 'success' : 'neutral'">
                Auto-renew {{ $service->auto_renew_enabled ? 'Enabled' : 'Disabled' }}
            </x-status-badge>
        </div>
        <div class="service-metric-grid">
            <div class="service-field">
                <div class="service-field__label">Provisioned</div>
                <div class="service-field__value">{{ $service->provisioned_at?->toDateTimeString() ?? '-' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Expires</div>
                <div class="service-field__value">{{ $service->expires_at?->toDateTimeString() ?? '-' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Renewal Policy</div>
                <div class="service-field__value">{{ $autoRenewalPolicy['allowed'] ? 'Allowed' : 'Disabled' }}</div>
            </div>
            <div class="service-field">
                <div class="service-field__label">Retry Policy</div>
                <div class="service-field__value">{{ $autoRenewalPolicy['retry_delay_minutes'] }}m / {{ $autoRenewalPolicy['max_attempts'] }} attempts</div>
            </div>
        </div>
    </div>
</div>

<div class="panel" id="billing">
    <div class="service-section-title">
        <h2>Billing Context</h2>
    </div>
    <div class="service-context-grid">
        <div class="service-field">
            <div class="service-field__label">Customer</div>
            <div class="service-field__value">
                <a href="/admin/customers/{{ $service->user_id }}">{{ $service->user?->email ?? '-' }}</a>
            </div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Order</div>
            <div class="service-field__value">
                @if ($service->order)
                    <a href="/admin/orders/{{ $service->order->id }}">{{ $service->order->order_number }}</a>
                @else
                    -
                @endif
            </div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Order ID</div>
            <div class="service-field__value">{{ $service->order_id ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Order Item</div>
            <div class="service-field__value">{{ $service->order_item_id ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Paid Amount</div>
            <div class="service-field__value">
                {{ $service->orderItem ? number_format($service->orderItem->subtotal_amount).' '.$service->orderItem->currency : '-' }}
            </div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Ledger</div>
            <div class="service-field__value">
                {{ $orderLedger ? number_format($orderLedger->amount).' '.$orderLedger->currency : '-' }}
            </div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="service-section-title">
        <h2>Related Invoices</h2>
        <span class="muted">Recent invoices for this customer</span>
    </div>
    <div class="data-table data-table--compact">
        <table>
            <thead><tr><th>Invoice</th><th>Status</th><th>Total</th><th>Paid</th><th>Description</th></tr></thead>
            <tbody>
                @forelse ($relatedInvoices as $invoice)
                    <tr>
                        <td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td>
                        <td><x-status-badge :tone="$invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'neutral' : 'warning')">{{ $invoice->status }}</x-status-badge></td>
                        <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                        <td>{{ $invoice->paid_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $invoice->description ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No invoices for this customer.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel" id="provider">
    <div class="service-section-title">
        <h2>Provider Mapping</h2>
    </div>
    <div class="service-context-grid">
        <div class="service-field">
            <div class="service-field__label">Driver</div>
            <div class="service-field__value">{{ $provider['driver'] ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Provider Account</div>
            <div class="service-field__value">{{ $route['account_slug'] ?? $provider['account_slug'] ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Location</div>
            <div class="service-field__value">{{ $route['billing_group_id'] ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Kind</div>
            <div class="service-field__value">{{ $provider['options']['kind'] ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Protocol</div>
            <div class="service-field__value">{{ $provider['options']['protocol'] ?? '-' }}</div>
        </div>
        <div class="service-field">
            <div class="service-field__label">Node Selector</div>
            <div class="service-field__value">{{ $route['node_selector_type'] ?? '-' }}</div>
        </div>
    </div>
</div>

@can('renewals.manage')
    <div class="panel">
        <div class="service-section-title">
            <h2>Admin Auto-renew Control</h2>
        </div>
        <form method="POST" action="/admin/services/{{ $service->id }}/auto-renew" class="invoice-filter-form">
            @csrf
            <input type="hidden" name="enabled" value="{{ $service->auto_renew_enabled ? '0' : '1' }}">
            <div class="invoice-filter-fields admin-filter-fields--1">
                <label class="invoice-filter-field" for="auto-renew-reason">
                    <span>Reason</span>
                    <textarea id="auto-renew-reason" name="reason" rows="2" required placeholder="Required audit reason"></textarea>
                </label>
            </div>
            <div class="invoice-filter-actions">
                <button class="button secondary" type="submit">{{ $service->auto_renew_enabled ? 'Disable Auto-renew' : 'Enable Auto-renew' }}</button>
            </div>
        </form>
    </div>
@endcan

@can('wallets.adjust')
<div class="panel">
    <div class="service-section-title">
        <h2>Refund Credit</h2>
    </div>
    <form method="POST" action="/admin/services/{{ $service->id }}/refund-credit" class="invoice-filter-form">
        @csrf
        <div class="invoice-filter-fields admin-filter-fields--4">
            <label class="invoice-filter-field" for="refund-amount"><span>Amount</span><input id="refund-amount" name="amount" type="number" min="1" required></label>
            <label class="invoice-filter-field" for="refund-currency"><span>Currency</span><input id="refund-currency" name="currency" value="VND" maxlength="3" required></label>
            <label class="invoice-filter-field" for="refund-reference"><span>Reference</span><input id="refund-reference" name="reference" placeholder="Optional reference"></label>
            <label class="invoice-filter-field" for="refund-reason"><span>Reason</span><input id="refund-reason" name="reason" required></label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Credit Refund</button>
        </div>
    </form>
</div>
@endcan

<div class="panel" id="jobs">
    <div class="service-section-title">
        <h2>Provisioning Jobs</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th></tr></thead>
            <tbody>
                @forelse ($service->provisioningJobs as $job)
                    <tr>
                        <td>{{ $job->idempotency_key }}</td>
                        <td>{{ $job->type }}</td>
                        <td><x-status-badge :tone="$job->status === 'processed' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning')">{{ $job->status }}</x-status-badge></td>
                        <td>{{ $job->attempts }}</td>
                        <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $job->last_error ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No provisioning jobs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="service-section-title">
        <h2>Provider Action Jobs</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Reference</th><th>Action</th><th>Status</th><th>Attempts</th><th>Available</th><th>Processed</th><th>Error</th></tr></thead>
            <tbody>
                @forelse ($service->providerActionJobs as $job)
                    <tr>
                        <td>{{ $job->idempotency_key }}</td>
                        <td>{{ $job->action }}</td>
                        <td><x-status-badge :tone="$job->status === 'processed' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning')">{{ $job->status }}</x-status-badge></td>
                        <td>{{ $job->attempts }}/{{ $job->max_attempts }}</td>
                        <td>{{ $job->available_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $job->processed_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $job->last_error ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No provider action jobs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="service-section-title">
        <h2>Auto-Renewal Attempts</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Status</th><th>Attempts</th><th>Target Expiry</th><th>Renewed Expiry</th><th>Next Retry</th><th>Amount</th><th>Error</th></tr></thead>
            <tbody>
                @forelse ($service->autoRenewalAttempts as $attempt)
                    <tr>
                        <td>{{ $attempt->status }}</td>
                        <td>{{ $attempt->attempts }} / {{ $autoRenewalPolicy['max_attempts'] }}</td>
                        <td>{{ $attempt->expires_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $attempt->renewed_expires_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $attempt->next_attempt_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $attempt->amount ? $attempt->amount.' '.$attempt->currency : '-' }}</td>
                        <td>{{ $attempt->last_error ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No auto-renewal attempts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="service-section-title">
        <h2>Cancellation Requests</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Mode</th><th>Status</th><th>Requested</th><th>Completed</th><th>Reason</th><th>Provider Job</th></tr></thead>
            <tbody>
                @forelse ($service->cancellations as $cancellation)
                    <tr>
                        <td>{{ $cancellation->mode }}</td>
                        <td>{{ $cancellation->status }}</td>
                        <td>{{ $cancellation->requested_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $cancellation->completed_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $cancellation->reason ?? '-' }}</td>
                        <td>{{ $cancellation->provider_action_job_id ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No cancellation requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="service-section-title">
        <h2>Provider Callbacks</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Event</th><th>Action</th><th>Status</th><th>Processing</th><th>Received</th><th>Error</th></tr></thead>
            <tbody>
                @forelse ($service->providerCallbackEvents as $event)
                    <tr>
                        <td>{{ $event->provider_event_id ?? $event->id }}</td>
                        <td>{{ $event->action ?? '-' }}</td>
                        <td>{{ $event->provider_status ?? '-' }}</td>
                        <td>{{ $event->processing_status }}</td>
                        <td>{{ $event->processed_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $event->error ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No provider callbacks yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel" id="logs">
    <div class="service-section-title">
        <h2>Execution Logs</h2>
    </div>
    <div class="data-table">
        <table>
            <thead><tr><th>Created</th><th>Action</th><th>Driver</th><th>Endpoint</th><th>Status</th><th>HTTP</th><th>Duration</th><th>Error</th><th>Request</th><th>Response</th></tr></thead>
            <tbody>
                @forelse ($service->provisioningExecutionLogs as $log)
                    <tr>
                        <td>{{ $log->created_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->driver }}</td>
                        <td>{{ $log->endpoint ?? '-' }}</td>
                        <td><x-status-badge :tone="$log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'neutral')">{{ $log->status }}</x-status-badge></td>
                        <td>{{ $log->http_status ?? '-' }}</td>
                        <td>{{ $log->duration_ms }}ms</td>
                        <td>{{ $log->error_code ?? $log->error_message ?? '-' }}</td>
                        <td><pre class="service-json">{{ json_encode($log->request_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                        <td><pre class="service-json">{{ json_encode($log->response_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="muted">No execution logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
