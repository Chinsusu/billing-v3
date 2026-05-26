@extends('layouts.admin', ['title' => 'Ops Health'])
@section('content')
<x-page-header
    title="Ops Health"
    subtitle="Monitor scheduler freshness, queue pressure, provider actions, and bank integration readiness."
>
    <x-slot:actions>
        <a class="button secondary" href="/admin/scheduled-task-runs">Scheduled Task Runs</a>
        <a class="button secondary" href="/admin/ops-alert-events">Ops Alert Events</a>
    </x-slot:actions>
</x-page-header>

<div class="ops-dashboard-grid">
    <x-stat-card
        label="Provisioning Queue"
        :value="$provisioningQueue['status']"
        :tone="$provisioningQueue['failed'] > 0 ? 'danger' : ($provisioningQueue['pending'] > 0 ? 'warning' : 'success')"
        :meta="'pending: '.$provisioningQueue['pending'].' | processing: '.$provisioningQueue['processing'].' | failed: '.$provisioningQueue['failed']"
        href="/admin/provisioning-jobs"
    />
    <x-stat-card
        label="Provider Action Queue"
        :value="$providerActionQueue['status']"
        :tone="$providerActionQueue['failed'] > 0 ? 'danger' : ($providerActionQueue['pending'] > 0 ? 'warning' : 'success')"
        :meta="'pending: '.$providerActionQueue['pending'].' | processing: '.$providerActionQueue['processing'].' | failed: '.$providerActionQueue['failed']"
        href="/admin/provider-action-jobs"
    />
    <x-stat-card label="Overdue Active Services" :value="$overdueActiveServiceCount" :tone="$overdueActiveServiceCount > 0 ? 'danger' : 'success'" href="/admin/services" />
    <x-stat-card label="Enabled Bank Integrations" :value="$enabledBankIntegrationCount" :tone="$enabledBankIntegrationCount > 0 ? 'success' : 'warning'" href="/admin/bank-integrations" />
</div>

<div class="panel">
    <h2>Scheduled Tasks</h2>
    <div class="data-table">
        <table>
            <thead><tr><th>Task</th><th>Health</th><th>Last Run</th><th>Duration</th><th>Exit</th><th>Message</th></tr></thead>
            <tbody>
                @foreach ($tasks as $task => $health)
                    <tr>
                        <td><a href="/admin/scheduled-task-runs?task={{ $task }}">{{ $task }}</a></td>
                        <td>
                            <x-status-badge :tone="$health['status'] === 'ok' ? 'success' : 'danger'">
                                {{ $health['status'] }}
                            </x-status-badge>
                        </td>
                        <td>
                            @if ($health['lastRun'])
                                <a href="/admin/scheduled-task-runs/{{ $health['lastRun']->id }}">{{ $health['lastRun']->started_at?->toDateTimeString() ?? '-' }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $health['lastRun']?->duration_ms ?? '-' }}</td>
                        <td>{{ $health['lastRun']?->exit_code ?? '-' }}</td>
                        <td>{{ $health['message'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
