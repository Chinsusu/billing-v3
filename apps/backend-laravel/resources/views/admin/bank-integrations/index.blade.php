@extends('layouts.admin', ['title' => 'Bank Integrations'])
@section('content')
<x-page-header
    title="Bank Integrations"
>
    <x-slot:actions>
        <a class="button" href="/admin/bank-integrations/create">Create Bank Integration</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    @if ($bankIntegrations->isEmpty())
        <x-empty-state title="No bank integrations yet." message="Add your private bank API endpoint before enabling QR payment reconciliation." />
    @else
        <div class="data-table">
            <table>
                <thead><tr><th>Name</th><th>Provider</th><th>Endpoint</th><th>Account</th><th>API Key</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($bankIntegrations as $integration)
                        <tr>
                            <td>{{ $integration->name }}</td>
                            <td><x-status-badge tone="neutral">{{ $integration->provider }}</x-status-badge></td>
                            <td>{{ $integration->transactionsUrl() }}</td>
                            <td>{{ $integration->account_number ?? '-' }}</td>
                            <td>
                                <x-status-badge :tone="$integration->api_key_last_four ? 'success' : 'warning'">
                                    {{ $integration->api_key_last_four ? 'Configured ...'.$integration->api_key_last_four : 'Not configured' }}
                                </x-status-badge>
                            </td>
                            <td>
                                <x-status-badge :tone="$integration->enabled ? 'success' : 'neutral'">
                                    {{ $integration->enabled ? 'Enabled' : 'Disabled' }}
                                </x-status-badge>
                                @if ($integration->last_sync_status)
                                    <x-status-badge tone="primary">{{ $integration->last_sync_status }}</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button secondary" href="/admin/bank-integrations/{{ $integration->id }}/edit">Edit</a>
                                    <form method="POST" action="/admin/bank-integrations/{{ $integration->id }}/test">
                                        @csrf
                                        <button type="submit">Test Connection</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $bankIntegrations->links() }}
    @endif
</div>
@endsection
