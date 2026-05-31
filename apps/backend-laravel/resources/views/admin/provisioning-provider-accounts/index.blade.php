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

    .provider-account-server__groups {
        background: rgba(var(--primary-rgb), 0.025);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        grid-column: 1 / -1;
        overflow: hidden;
    }

    .provider-account-server__groups-header {
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-muted);
        display: flex;
        font-size: 0.82rem;
        justify-content: space-between;
        padding: 10px 12px;
    }

    .provider-account-server__groups-table {
        display: grid;
        max-height: 260px;
        overflow: auto;
    }

    .provider-account-server__groups-row {
        display: grid;
        gap: 12px;
        grid-template-columns: 110px minmax(180px, 1.2fr) minmax(180px, 1fr) 110px 110px minmax(220px, 1fr);
        padding: 11px 12px;
    }

    .provider-account-server__groups-row + .provider-account-server__groups-row {
        border-top: 1px solid var(--border-color);
    }

    .provider-account-server__groups-row--head {
        background: rgba(var(--primary-rgb), 0.06);
        color: var(--text-muted);
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: uppercase;
    }

    .provider-account-server__groups-cell {
        color: var(--text-heading);
        font-size: 0.84rem;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .provider-account-server__groups-cell--mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    }

    .provider-account-server__groups-state {
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.76rem;
        font-weight: 700;
        line-height: 1;
        padding: 6px 8px;
        width: fit-content;
    }

    .provider-account-server__groups-state--sellable {
        background: rgba(var(--success), 0.14);
        color: rgb(var(--success));
    }

    .provider-account-server__groups-state--limited {
        background: rgba(var(--warning), 0.16);
        color: rgb(var(--warning));
    }

    .provider-account-server__groups-state--exhausted {
        background: rgba(var(--danger), 0.14);
        color: rgb(var(--danger));
    }

    .provider-account-server__groups-empty {
        color: var(--text-muted);
        font-size: 0.86rem;
        padding: 18px 12px;
        text-align: center;
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
                                @if ($driver === 'cloudmini_v3')
                                    <button
                                        type="button"
                                        class="secondary button-soft"
                                        data-cloudmini-groups-button
                                        data-groups-url="/admin/provisioning-provider-accounts/{{ $account->id }}/inventory/groups"
                                        aria-controls="cloudmini-groups-{{ $account->id }}"
                                        aria-expanded="false"
                                    >
                                        Load Groups
                                    </button>
                                @endif
                                <a class="button secondary" href="/admin/provisioning-provider-accounts/{{ $account->id }}/edit">Edit</a>
                                <form method="POST" action="/admin/provisioning-provider-accounts/{{ $account->id }}/test">
                                    @csrf
                                    <button type="submit">Test</button>
                                </form>
                            </div>

                            @if ($driver === 'cloudmini_v3')
                                <div class="provider-account-server__groups" id="cloudmini-groups-{{ $account->id }}" data-cloudmini-groups-panel hidden>
                                    <div class="provider-account-server__groups-header">
                                        <span>Inventory groups for this server</span>
                                        <span data-cloudmini-groups-status>Not loaded</span>
                                    </div>
                                    <div data-cloudmini-groups-output></div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif

<script>
    document.querySelectorAll('[data-cloudmini-groups-button]').forEach((button) => {
        const panel = document.getElementById(button.getAttribute('aria-controls'));
        const status = panel?.querySelector('[data-cloudmini-groups-status]');
        const output = panel?.querySelector('[data-cloudmini-groups-output]');

        if (!panel || !status || !output) {
            return;
        }

        const setPanelVisibility = (visible) => {
            panel.hidden = !visible;
            button.setAttribute('aria-expanded', visible ? 'true' : 'false');
        };

        button.addEventListener('click', async () => {
            if (button.dataset.loaded === 'true') {
                const nextVisible = panel.hidden;
                setPanelVisibility(nextVisible);
                button.textContent = nextVisible ? 'Hide Groups' : 'Show Groups';
                return;
            }

            setPanelVisibility(true);
            button.disabled = true;
            button.textContent = 'Loading...';
            status.textContent = 'Loading';
            output.innerHTML = '<div class="provider-account-server__groups-empty">Loading inventory groups...</div>';

            try {
                const response = await fetch(button.dataset.groupsUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const groups = Array.isArray(payload.groups) ? payload.groups : [];
                renderCloudminiGroups(output, groups);
                status.textContent = `${groups.length} groups`;
                button.dataset.loaded = 'true';
                button.textContent = 'Hide Groups';
            } catch (error) {
                status.textContent = 'Load failed';
                output.innerHTML = '<div class="provider-account-server__groups-empty">Could not load groups from this server.</div>';
                button.textContent = 'Retry Groups';
            } finally {
                button.disabled = false;
            }
        });
    });

    function cloudminiText(value) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return String(value);
    }

    function renderCloudminiGroups(container, groups) {
        if (groups.length === 0) {
            container.innerHTML = '<div class="provider-account-server__groups-empty">No inventory groups returned.</div>';
            return;
        }

        const table = document.createElement('div');
        table.className = 'provider-account-server__groups-table';

        const header = document.createElement('div');
        header.className = 'provider-account-server__groups-row provider-account-server__groups-row--head';
        ['Kind', 'Name', 'Billing Group ID', 'Capacity', 'State', 'Provider ID'].forEach((label) => {
            const cell = document.createElement('div');
            cell.textContent = label;
            header.appendChild(cell);
        });
        table.appendChild(header);

        groups.forEach((group) => {
            const row = document.createElement('div');
            row.className = 'provider-account-server__groups-row';
            const capacity = group.allocatable_units ?? group.free_ip_count ?? '-';
            const state = cloudminiText(group.sell_state);
            const cells = [
                [group.kind, true],
                [group.name, false],
                [group.billing_group_id, true],
                [capacity, true],
                [state, false, state],
                [group.id, true],
            ];

            cells.forEach(([value, mono, stateValue]) => {
                const cell = document.createElement('div');
                cell.className = `provider-account-server__groups-cell${mono ? ' provider-account-server__groups-cell--mono' : ''}`;
                cell.title = cloudminiText(value);

                if (stateValue) {
                    const badge = document.createElement('span');
                    const stateClass = cloudminiText(stateValue).toLowerCase().replace(/[^a-z0-9_-]/g, '-');
                    badge.className = `provider-account-server__groups-state provider-account-server__groups-state--${stateClass}`;
                    badge.textContent = cloudminiText(value);
                    cell.appendChild(badge);
                } else {
                    cell.textContent = cloudminiText(value);
                }

                row.appendChild(cell);
            });

            table.appendChild(row);
        });

        container.replaceChildren(table);
    }
</script>
@endsection
