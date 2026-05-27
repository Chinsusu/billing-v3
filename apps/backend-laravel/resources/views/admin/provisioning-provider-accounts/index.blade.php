@extends('layouts.admin', ['title' => 'Provisioning Provider Accounts'])
@section('content')
<x-page-header
    title="Provider Accounts"
>
    <x-slot:actions>
        <a class="button" href="/admin/provisioning-provider-accounts/create">Create Provider Account</a>
    </x-slot:actions>
</x-page-header>

<div class="panel">
    @if ($providerAccounts->isEmpty())
        <x-empty-state title="No provisioning provider accounts yet." message="Create one account per provider endpoint or provider API key." />
    @else
        <div class="data-table">
            <table>
                <thead><tr><th>Name</th><th>Slug</th><th>Driver</th><th>Endpoint</th><th>Auth</th><th>API Key</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($providerAccounts as $account)
                        <tr>
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->slug }}</td>
                            <td><x-status-badge tone="neutral">{{ $account->driver }}</x-status-badge></td>
                            <td>{{ $account->endpointUrl() ?? '-' }}</td>
                            <td>{{ $account->auth_type }}{{ $account->auth_header_name ? ' ('.$account->auth_header_name.')' : '' }}</td>
                            <td>
                                <x-status-badge :tone="$account->api_key_last_four ? 'success' : 'warning'">
                                    {{ $account->api_key_last_four ? 'Configured ...'.$account->api_key_last_four : 'Not configured' }}
                                </x-status-badge>
                            </td>
                            <td>
                                <x-status-badge :tone="$account->enabled ? 'success' : 'neutral'">
                                    {{ $account->enabled ? 'Enabled' : 'Disabled' }}
                                </x-status-badge>
                                @if ($account->last_test_status)
                                    <x-status-badge tone="primary">{{ $account->last_test_status }}</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button secondary" href="/admin/provisioning-provider-accounts/{{ $account->id }}/edit">Edit</a>
                                    <form method="POST" action="/admin/provisioning-provider-accounts/{{ $account->id }}/test">
                                        @csrf
                                        <button type="submit">Test</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $providerAccounts->links() }}
    @endif
</div>
@endsection
