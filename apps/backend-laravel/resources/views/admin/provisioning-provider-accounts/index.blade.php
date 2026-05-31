@extends('layouts.admin', ['title' => 'Provisioning Provider Accounts'])
@php
    $driverLabels = [
        'cloudmini_v3' => 'Cloudmini V3',
        'generic_http' => 'Generic HTTP',
        'sandbox' => 'Sandbox',
    ];
    $driverDescriptions = [
        'cloudmini_v3' => 'Server managers that share the same Cloudmini API contract. Each row is one base URL and API key.',
        'generic_http' => 'Legacy HTTP endpoints with custom request and response mapping.',
        'sandbox' => 'Local test providers used for development and smoke checks.',
    ];
    $driverOrder = ['cloudmini_v3', 'generic_http', 'sandbox'];
    $groupedAccounts = collect($providerAccounts->groupBy('driver')->all());
    $orderedGroups = collect($driverOrder)
        ->filter(fn (string $driver): bool => $groupedAccounts->has($driver))
        ->mapWithKeys(fn (string $driver): array => [$driver => $groupedAccounts->get($driver)])
        ->merge($groupedAccounts->except($driverOrder));
@endphp

@section('content')
<x-page-header title="Provider Accounts">
    <x-slot:actions>
        <a class="button" href="/admin/provisioning-provider-accounts/create">Create Provider Account</a>
    </x-slot:actions>
</x-page-header>

<style>
    .provider-account-groups {
        display: grid;
        gap: 18px;
    }

    .provider-account-group {
        padding: 0;
    }

    .provider-account-group__header {
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
    }

    .provider-account-group__title {
        align-items: center;
        display: flex;
        gap: 10px;
        margin-bottom: 4px;
    }

    .provider-account-group__title h2 {
        color: var(--text-heading);
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: 0;
    }

    .provider-account-group__meta {
        color: var(--text-muted);
        font-size: 0.86rem;
        line-height: 1.45;
    }

    .provider-account-server-list {
        display: grid;
    }

    .provider-account-server {
        align-items: center;
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(240px, 1.2fr) minmax(280px, 1.6fr) minmax(210px, 1fr) auto;
        padding: 16px 20px;
    }

    .provider-account-server + .provider-account-server {
        border-top: 1px solid var(--border-color);
    }

    .provider-account-server__identity {
        min-width: 0;
    }

    .provider-account-server__name {
        color: var(--text-heading);
        font-size: 0.95rem;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .provider-account-server__slug,
    .provider-account-server__label {
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .provider-account-server__value {
        color: var(--text-heading);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 0.86rem;
        margin-top: 5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .provider-account-server__stack {
        display: grid;
        gap: 8px;
        min-width: 0;
    }

    .provider-account-server__badges {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .provider-account-server__actions {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        white-space: nowrap;
    }

    @media (max-width: 1280px) {
        .provider-account-server {
            grid-template-columns: 1fr 1fr;
        }

        .provider-account-server__actions {
            justify-content: flex-start;
        }
    }
</style>

@if ($providerAccounts->isEmpty())
    <div class="panel">
        <x-empty-state title="No provisioning provider accounts yet." message="Create one account per provider endpoint or provider API key." />
    </div>
@else
    <div class="provider-account-groups">
        @foreach ($orderedGroups as $driver => $accounts)
            <section class="panel provider-account-group" aria-labelledby="provider-account-group-{{ $driver }}">
                <div class="provider-account-group__header">
                    <div>
                        <div class="provider-account-group__title">
                            <h2 id="provider-account-group-{{ $driver }}">{{ $driverLabels[$driver] ?? $driver }}</h2>
                            <x-status-badge tone="neutral">{{ $accounts->count() }} {{ Str::plural('server', $accounts->count()) }}</x-status-badge>
                        </div>
                        <div class="provider-account-group__meta">{{ $driverDescriptions[$driver] ?? 'Provider endpoints configured for provisioning.' }}</div>
                    </div>
                    @if ($driver === 'cloudmini_v3')
                        <a class="button secondary button-soft" href="/admin/provisioning-provider-accounts/create">Add Cloudmini Server</a>
                    @endif
                </div>

                <div class="provider-account-server-list">
                    @foreach ($accounts as $account)
                        <article class="provider-account-server">
                            <div class="provider-account-server__identity">
                                <div class="provider-account-server__name">{{ $account->name }}</div>
                                <div class="provider-account-server__slug">{{ $account->slug }}</div>
                            </div>

                            <div class="provider-account-server__stack">
                                <div>
                                    <div class="provider-account-server__label">{{ $driver === 'cloudmini_v3' ? 'Base URL (Server)' : 'Endpoint' }}</div>
                                    <div class="provider-account-server__value" title="{{ $driver === 'cloudmini_v3' ? $account->base_url : ($account->endpointUrl() ?? '-') }}">
                                        {{ $driver === 'cloudmini_v3' ? ($account->base_url ?? '-') : ($account->endpointUrl() ?? '-') }}
                                    </div>
                                </div>
                                @if ($driver !== 'cloudmini_v3' && $account->provision_path)
                                    <div class="provider-account-server__value" title="{{ $account->provision_path }}">{{ $account->provision_path }}</div>
                                @endif
                            </div>

                            <div class="provider-account-server__stack">
                                <div class="provider-account-server__badges">
                                    <x-status-badge :tone="$account->api_key_last_four ? 'success' : 'warning'">
                                        {{ $account->api_key_last_four ? 'API key ...'.$account->api_key_last_four : 'API key missing' }}
                                    </x-status-badge>
                                    <x-status-badge tone="neutral">{{ $account->auth_type }}{{ $account->auth_header_name ? ' / '.$account->auth_header_name : '' }}</x-status-badge>
                                </div>
                                <div class="provider-account-server__badges">
                                    <x-status-badge :tone="$account->enabled ? 'success' : 'neutral'">{{ $account->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge>
                                    <x-status-badge tone="neutral">{{ $account->timeout_seconds }}s timeout</x-status-badge>
                                    @if ($account->last_test_status)
                                        <x-status-badge tone="primary">{{ $account->last_test_status }}</x-status-badge>
                                    @endif
                                </div>
                            </div>

                            <div class="provider-account-server__actions">
                                <a class="button secondary" href="/admin/provisioning-provider-accounts/{{ $account->id }}/edit">Edit</a>
                                <form method="POST" action="/admin/provisioning-provider-accounts/{{ $account->id }}/test">
                                    @csrf
                                    <button type="submit">Test</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
@endsection
