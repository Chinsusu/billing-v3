@extends('layouts.app', ['title' => 'Provisioning Provider Accounts'])
@section('content')
<div class="panel">
    <h1>Provisioning Provider Accounts</h1>
    <p><a class="button" href="/admin/provisioning-provider-accounts/create">Create Provider Account</a></p>
    <table>
        <thead><tr><th>Name</th><th>Slug</th><th>Driver</th><th>Endpoint</th><th>Auth</th><th>API Key</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse ($providerAccounts as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->slug }}</td>
                    <td>{{ $account->driver }}</td>
                    <td>{{ $account->endpointUrl() ?? '-' }}</td>
                    <td>{{ $account->auth_type }}{{ $account->auth_header_name ? ' ('.$account->auth_header_name.')' : '' }}</td>
                    <td>{{ $account->api_key_last_four ? 'Configured ...'.$account->api_key_last_four : 'Not configured' }}</td>
                    <td>{{ $account->enabled ? 'Enabled' : 'Disabled' }} {{ $account->last_test_status ? '('.$account->last_test_status.')' : '' }}</td>
                    <td><a class="button secondary" href="/admin/provisioning-provider-accounts/{{ $account->id }}/edit">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No provisioning provider accounts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $providerAccounts->links() }}
</div>
@endsection
