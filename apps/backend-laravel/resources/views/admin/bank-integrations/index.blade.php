@extends('layouts.app', ['title' => 'Bank Integrations'])
@section('content')
<div class="panel">
    <h1>Bank Integrations</h1>
    <p><a class="button" href="/admin/bank-integrations/create">Create Bank Integration</a></p>
    <table>
        <thead><tr><th>Name</th><th>Provider</th><th>Endpoint</th><th>Account</th><th>API Key</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse ($bankIntegrations as $integration)
                <tr>
                    <td>{{ $integration->name }}</td>
                    <td>{{ $integration->provider }}</td>
                    <td>{{ $integration->transactionsUrl() }}</td>
                    <td>{{ $integration->account_number ?? '-' }}</td>
                    <td>{{ $integration->api_key_last_four ? 'Configured ...'.$integration->api_key_last_four : 'Not configured' }}</td>
                    <td>{{ $integration->enabled ? 'Enabled' : 'Disabled' }} {{ $integration->last_sync_status ? '('.$integration->last_sync_status.')' : '' }}</td>
                    <td>
                        <a class="button secondary" href="/admin/bank-integrations/{{ $integration->id }}/edit">Edit</a>
                        <form method="POST" action="/admin/bank-integrations/{{ $integration->id }}/test" style="display:inline">
                            @csrf
                            <button type="submit">Test Connection</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No bank integrations yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $bankIntegrations->links() }}
</div>
@endsection
