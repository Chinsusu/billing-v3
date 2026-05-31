@extends('layouts.admin', ['title' => 'Admin Services'])
@section('content')
@php
    $statusTone = fn (?string $status): string => match ($status) {
        'active', 'online', 'processed' => 'success',
        'pending_provision', 'processing', 'pending' => 'warning',
        'cancelled', 'expired', 'failed' => 'danger',
        default => 'neutral',
    };
    $serviceHost = function ($service): string {
        $config = $service->config ?? [];

        return $config['hostname']
            ?? $config['host']
            ?? $config['ip']
            ?? $config['server_ip']
            ?? $service->external_id
            ?? '-';
    };
    $servicePort = fn ($config, array $keys): ?string => collect($keys)
        ->map(fn ($key) => $config[$key] ?? null)
        ->first(fn ($value) => $value !== null && $value !== '');
    $locationFor = function ($service): string {
        $config = $service->config ?? [];
        $provider = $service->meta['provider'] ?? [];
        $route = collect($provider['routes'] ?? [])->first();

        return $config['location']
            ?? $config['region']
            ?? $config['group_name']
            ?? ($route['billing_group_id'] ?? null)
            ?? '-';
    };
@endphp
<style>
    .service-inventory-table table {
        min-width: 1560px;
    }

    .service-inventory-table tbody tr:nth-child(even) {
        background: rgba(var(--secondary), 0.035);
    }

    .service-id-link {
        font-weight: 800;
    }

    .service-stack {
        display: grid;
        gap: 6px;
    }

    .service-chip {
        align-items: center;
        background: rgba(var(--secondary), 0.16);
        border-radius: 999px;
        color: var(--text-heading);
        display: inline-flex;
        font-weight: 700;
        min-height: 24px;
        max-width: 260px;
        overflow: hidden;
        padding: 4px 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
        width: fit-content;
    }

    .service-chip--muted {
        color: var(--text-muted);
        font-weight: 600;
    }

    .service-inventory-table .table-actions {
        flex-wrap: nowrap;
    }
</style>

<x-page-header
    title="Services"
    eyebrow="Resources"
>
    <x-slot:actions>
        <a href="/admin/provider-action-jobs" class="button secondary button-soft">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V6l-8-4Zm0 2.2L18 7v5c0 3.9-2.4 7.6-6 8.8-3.6-1.2-6-4.9-6-8.8V7l6-2.8Zm-1 4.3v4.1l3.5 2.1.9-1.5-2.6-1.5V8.5H11Z"/></svg>
            <span>Provider Jobs</span>
        </a>
        @can('renewals.view')
            <a href="/admin/renewals" class="button secondary button-soft">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 6V3L8 7l4 4V8c2.8 0 5 2.2 5 5 0 1-.3 1.9-.8 2.7l1.5 1.5A6.96 6.96 0 0 0 19 13c0-3.9-3.1-7-7-7Zm-5 5.3A6.96 6.96 0 0 0 5 16c0 3.9 3.1 7 7 7v3l4-4-4-4v3c-2.8 0-5-2.2-5-5 0-1 .3-1.9.8-2.7L7 11.3Z"/></svg>
                <span>Renewals</span>
            </a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="panel service-inventory-panel">
    <div class="data-table service-inventory-table">
        <table>
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Hostname/IP</th>
                    <th>User/Pass</th>
                    <th>Port</th>
                    <th>Location</th>
                    <th>Member</th>
                    <th>Plan</th>
                    <th>Date</th>
                    <th>Auto-renew</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($services as $service)
                    @php
                        $config = $service->config ?? [];
                        $latestAutoRenewalAttempt = $service->latestAutoRenewalAttemptForCurrentExpiry();
                        $httpPort = $servicePort($config, ['port_http', 'http_port', 'port_https', 'https_port']);
                        $socksPort = $servicePort($config, ['port_socks', 'socks_port']);
                        $daysRemaining = $service->expires_at ? now()->startOfDay()->diffInDays($service->expires_at->copy()->startOfDay(), false) : null;
                    @endphp
                    <tr>
                        <td>
                            <a class="service-id-link" href="/admin/services/{{ $service->id }}">
                                {{ \Illuminate\Support\Str::limit($service->id, 8, '') }}
                            </a>
                            <div class="muted">{{ $service->product_code }}</div>
                        </td>
                        <td>
                            <div class="service-stack">
                                <span class="service-chip">{{ $serviceHost($service) }}</span>
                                @if ($service->external_id && $service->external_id !== $serviceHost($service))
                                    <span class="service-chip service-chip--muted">{{ $service->external_id }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="service-stack">
                                <span class="service-chip">{{ $config['username'] ?? '-' }}</span>
                                <span class="service-chip service-chip--muted">{{ $config['password'] ?? '-' }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="service-stack">
                                <span>HTTP: <strong>{{ $httpPort ?? '-' }}</strong></span>
                                <span>SOCKS: <strong>{{ $socksPort ?? '-' }}</strong></span>
                            </div>
                        </td>
                        <td>{{ $locationFor($service) }}</td>
                        <td>
                            <a href="/admin/customers/{{ $service->user_id }}">{{ $service->user?->name ?? 'Customer' }}</a>
                            <div class="muted">{{ $service->user?->email ?? '-' }}</div>
                        </td>
                        <td>
                            <a href="/admin/services/{{ $service->id }}">{{ $service->product_name }}</a>
                            <div class="muted">
                                {{ $service->orderItem ? number_format($service->orderItem->subtotal_amount).' '.$service->orderItem->currency : '-' }}
                            </div>
                        </td>
                        <td>
                            <div>{{ $service->provisioned_at?->format('d-m-Y H:i') ?? '-' }}</div>
                            <div>{{ $service->expires_at?->format('d-m-Y H:i') ?? '-' }}</div>
                            @if ($daysRemaining !== null)
                                <x-status-badge :tone="$daysRemaining < 0 ? 'danger' : ($daysRemaining <= 7 ? 'warning' : 'success')">
                                    {{ $daysRemaining < 0 ? 'Expired' : $daysRemaining.' days left' }}
                                </x-status-badge>
                            @endif
                        </td>
                        <td>
                            <x-status-badge :tone="$service->auto_renew_enabled ? 'success' : 'neutral'">
                                {{ $service->auto_renew_enabled ? 'On' : 'Off' }}
                            </x-status-badge>
                            <div class="muted">{{ $latestAutoRenewalAttempt?->status ?? '-' }}</div>
                        </td>
                        <td><x-status-badge :tone="$statusTone($service->status)">{{ $service->status }}</x-status-badge></td>
                        <td>
                            <div class="table-actions">
                                <a class="button secondary button-soft button-compact" href="/admin/services/{{ $service->id }}">Details</a>
                                <form method="POST" action="/admin/services/{{ $service->id }}/sync-provider">
                                    @csrf
                                    <button class="button secondary button-compact" type="submit">Sync Provider</button>
                                </form>
                                <form method="POST" action="/admin/services/{{ $service->id }}/cancel-provider">
                                    @csrf
                                    <button class="button secondary button-compact" type="submit">Cancel Provider</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="muted">No services yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $services->links() }}
</div>
@endsection
