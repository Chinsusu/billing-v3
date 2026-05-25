@extends('layouts.app')

@section('content')
    <div class="panel">
        <h1>API Keys</h1>
        <p class="muted">Create scoped bearer tokens for first-party automation.</p>

        @if (session('plain_api_key'))
            <div class="panel">
                <strong>New API key</strong>
                <p class="muted">Copy this key now. It will not be shown again.</p>
                <input type="text" readonly value="{{ session('plain_api_key') }}">
            </div>
        @endif

        <form method="POST" action="/api-keys">
            @csrf
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" placeholder="Deployment script">

            <label>Scopes</label>
            @foreach ($scopes as $scope)
                <label style="font-weight:400">
                    <input type="checkbox" name="scopes[]" value="{{ $scope }}" style="width:auto" @checked(in_array($scope, old('scopes', []), true))>
                    {{ $scope }}
                </label>
            @endforeach

            <button type="submit">Create API Key</button>
        </form>
    </div>

    <div class="panel">
        <h2>Existing Keys</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Prefix</th>
                    <th>Scopes</th>
                    <th>Last Used</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($apiKeys as $apiKey)
                    <tr>
                        <td>{{ $apiKey->name }}</td>
                        <td>{{ $apiKey->prefix }}</td>
                        <td>{{ implode(', ', $apiKey->scopes ?? []) ?: '-' }}</td>
                        <td>{{ $apiKey->last_used_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $apiKey->revoked_at ? 'Revoked' : 'Active' }}</td>
                        <td>
                            @if (! $apiKey->revoked_at)
                                <form method="POST" action="/api-keys/{{ $apiKey->id }}/revoke">
                                    @csrf
                                    <button type="submit" class="danger">Revoke</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No API keys yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
