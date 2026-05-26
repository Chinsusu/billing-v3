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

            <label for="rate_limit_per_minute">Rate Limit Per Minute</label>
            <input id="rate_limit_per_minute" name="rate_limit_per_minute" type="number" min="1" max="{{ $maxRateLimit }}" value="{{ old('rate_limit_per_minute', $defaultRateLimit) }}">
            <p class="muted">Maximum: {{ $maxRateLimit }} requests per minute.</p>

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
                    <th>Limit</th>
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
                        <td>{{ $apiKey->rate_limit_per_minute }}/min</td>
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
                    <tr><td colspan="7">No API keys yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Recent Usage</h2>
        <table>
            <thead>
                <tr>
                    <th>When</th>
                    <th>Key</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Error</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentUsageLogs as $usageLog)
                    <tr>
                        <td>{{ $usageLog->created_at?->toDateTimeString() ?? '-' }}</td>
                        <td>{{ $usageLog->api_key_prefix }}</td>
                        <td>{{ $usageLog->route_name ?? $usageLog->path }}</td>
                        <td>{{ $usageLog->status_code }}</td>
                        <td>{{ $usageLog->error_reason ?? '-' }}</td>
                        <td>{{ $usageLog->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No API usage recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
