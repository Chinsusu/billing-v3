@extends('layouts.admin', ['title' => 'Admin User Detail'])
@section('content')
<div class="panel">
    <h1>User Authorization</h1>
    <p><a href="/admin/users">Back to Users</a></p>
    @can('users.manage')
        <p><a class="button" href="/admin/users/{{ $managedUser->id }}/edit">Edit Authorization</a></p>
    @endcan
    <table>
        <tbody>
            <tr><th>Name</th><td>{{ $managedUser->name }}</td></tr>
            <tr><th>Email</th><td>{{ $managedUser->email }}</td></tr>
            <tr><th>Roles</th><td>{{ implode(', ', $roles) ?: '-' }}</td></tr>
            <tr><th>Direct Permissions</th><td>{{ implode(', ', $directPermissions) ?: '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Effective Permissions</h2>
    <p>{{ implode(', ', $effectivePermissions) ?: '-' }}</p>
</div>

<div class="panel">
    <h2>Security</h2>
    <table>
        <tbody>
            <tr><th>Status</th><td>{{ $managedUser->disabled_at ? 'Disabled' : 'Enabled' }}</td></tr>
            <tr><th>Disabled Reason</th><td>{{ $managedUser->disabled_reason ?: '-' }}</td></tr>
            <tr><th>Force Password Reset</th><td>{{ $managedUser->force_password_reset_at ? 'Required' : 'Not required' }}</td></tr>
            <tr><th>Invited At</th><td>{{ $managedUser->invited_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            <tr><th>Last Password Reset</th><td>{{ $managedUser->last_password_reset_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            <tr><th>Last Login</th><td>{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            <tr><th>Last Login IP</th><td>{{ $managedUser->last_login_ip ?: '-' }}</td></tr>
            <tr><th>Last Login User Agent</th><td>{{ $managedUser->last_login_user_agent ?: '-' }}</td></tr>
            <tr><th>MFA</th><td>{{ $managedUser->mfa_enabled_at ? 'Enabled' : ($managedUser->mfa_required_at ? 'Required' : 'Not required') }}</td></tr>
        </tbody>
    </table>

    @can('users.manage')
        <div class="grid">
            <form method="POST" action="/admin/users/{{ $managedUser->id }}/security/send-reset-link">
                @csrf
                <p><button type="submit">Create Setup Link</button></p>
            </form>

            @if ($managedUser->force_password_reset_at)
                <form method="POST" action="/admin/users/{{ $managedUser->id }}/security/clear-force-password-reset">
                    @csrf
                    <p><button type="submit" class="secondary">Clear Forced Reset</button></p>
                </form>
            @else
                <form method="POST" action="/admin/users/{{ $managedUser->id }}/security/force-password-reset">
                    @csrf
                    <p><button type="submit">Force Password Reset</button></p>
                </form>
            @endif

            <form method="POST" action="/admin/users/{{ $managedUser->id }}/mfa/require">
                @csrf
                <p><button type="submit">Require MFA</button></p>
            </form>

            <form method="POST" action="/admin/users/{{ $managedUser->id }}/mfa/reset">
                @csrf
                <p><button type="submit" class="secondary">Reset MFA</button></p>
            </form>
        </div>

        @if ($managedUser->disabled_at)
            <form method="POST" action="/admin/users/{{ $managedUser->id }}/security/enable">
                @csrf
                <p><button type="submit">Enable User</button></p>
            </form>
        @else
            <form method="POST" action="/admin/users/{{ $managedUser->id }}/security/disable">
                @csrf
                <label>Disable Reason
                    <textarea name="reason" required>{{ old('reason') }}</textarea>
                </label>
                <p><button type="submit" class="danger">Disable User</button></p>
            </form>
        @endif
    @endcan
</div>

<div class="panel">
    <h2>Sessions</h2>
    <table>
        <thead><tr><th>Session</th><th>IP</th><th>User Agent</th><th>Last Activity</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($sessions as $session)
                <tr>
                    <td>{{ $session->id }}</td>
                    <td>{{ $session->ip_address ?: '-' }}</td>
                    <td>{{ $session->user_agent ?: '-' }}</td>
                    <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->toDateTimeString() }}</td>
                    <td>
                        @can('users.manage')
                            <form method="POST" action="/admin/users/{{ $managedUser->id }}/sessions/{{ $session->id }}/revoke">
                                @csrf
                                <button type="submit" class="danger">Revoke</button>
                            </form>
                        @else
                            -
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No active database sessions.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
