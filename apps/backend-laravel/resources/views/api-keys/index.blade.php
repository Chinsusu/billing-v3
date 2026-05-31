@extends('layouts.app')

@section('content')
    <x-page-header
        title="API Keys"
        subtitle="Create scoped bearer tokens for first-party automation and inspect recent usage."
    />

    @if (session('plain_api_key'))
        <div class="panel">
            <div class="form-section">
                <strong>New API key</strong>
                <p class="muted">Copy this key now. It will not be shown again.</p>
                <input type="text" readonly value="{{ session('plain_api_key') }}">
            </div>
        </div>
    @endif

    <div class="panel">
        <h2>Create key</h2>
        <form method="POST" action="/api-keys">
            @csrf
            <div class="form-section">
                <div>
                    <label for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name') }}" placeholder="Deployment script">
                </div>

                <div>
                    <label for="rate_limit_per_minute">Rate limit per minute</label>
                    <input id="rate_limit_per_minute" name="rate_limit_per_minute" type="number" min="1" max="{{ $maxRateLimit }}" value="{{ old('rate_limit_per_minute', $defaultRateLimit) }}">
                    <p class="field-help">Maximum: {{ $maxRateLimit }} requests per minute.</p>
                </div>

                <div>
                    <label>Scopes</label>
                    @foreach ($scopes as $scope)
                        <label style="font-weight:400; text-transform:none">
                            <input type="checkbox" name="scopes[]" value="{{ $scope }}" style="width:auto" @checked(in_array($scope, old('scopes', []), true))>
                            {{ $scope }}
                        </label>
                    @endforeach
                </div>

                <div class="action-row">
                    <button type="submit">Create API Key</button>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Existing Keys</h2>
        @if ($apiKeys->isEmpty())
            <x-empty-state title="No API keys yet." message="Create a key when a script or trusted internal tool needs API access." />
        @else
            <div class="data-table">
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
                        @foreach ($apiKeys as $apiKey)
                            <tr>
                                <td>{{ $apiKey->name }}</td>
                                <td>{{ $apiKey->prefix }}</td>
                                <td>{{ implode(', ', $apiKey->scopes ?? []) ?: '-' }}</td>
                                <td>{{ $apiKey->rate_limit_per_minute }}/min</td>
                                <td>{{ $apiKey->last_used_at?->toDateTimeString() ?? '-' }}</td>
                                <td>
                                    <x-status-badge :tone="$apiKey->revoked_at ? 'danger' : 'success'">
                                        {{ $apiKey->revoked_at ? 'Revoked' : 'Active' }}
                                    </x-status-badge>
                                </td>
                                <td>
                                    @if (! $apiKey->revoked_at)
                                        <form method="POST" action="/api-keys/{{ $apiKey->id }}/revoke">
                                            @csrf
                                            <button type="submit" class="danger">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="panel">
        <h2>Recent Usage</h2>
        @if ($recentUsageLogs->isEmpty())
            <x-empty-state title="No API usage recorded yet." message="Recent calls will appear here after a key is used." />
        @else
            <div class="data-table">
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
                        @foreach ($recentUsageLogs as $usageLog)
                            <tr>
                                <td>{{ $usageLog->created_at?->toDateTimeString() ?? '-' }}</td>
                                <td>{{ $usageLog->api_key_prefix }}</td>
                                <td>{{ $usageLog->route_name ?? $usageLog->path }}</td>
                                <td><x-status-badge :tone="$usageLog->status_code >= 400 ? 'danger' : 'success'">{{ $usageLog->status_code }}</x-status-badge></td>
                                <td>{{ $usageLog->error_reason ?? '-' }}</td>
                                <td>{{ $usageLog->ip_address ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
