@extends('layouts.admin', ['title' => 'Ops Alert Rules'])
@section('content')
<div class="panel">
    <h1>Ops Alert Rules</h1>
    <p><a class="button secondary" href="/admin/ops-alert-events">Ops Alert Events</a></p>
    <form method="POST" action="/admin/ops-alert-rules">
        @csrf
        <label>Name<input name="name" value="{{ old('name', 'Ops Health') }}" required></label>
        <label>Type
            <select name="type"><option value="ops_health">ops_health</option></select>
        </label>
        <label>Severity
            <select name="severity">
                <option value="warning">warning</option>
                <option value="critical">critical</option>
            </select>
        </label>
        <label>Cooldown minutes<input type="number" name="cooldown_minutes" min="1" max="1440" value="{{ old('cooldown_minutes', 15) }}" required></label>
        <label>Webhook URL<input name="webhook_url" placeholder="https://example.test/webhook"></label>
        <label>Webhook secret<input name="webhook_secret" placeholder="Leave blank unless setting a secret"></label>
        <label><input type="checkbox" name="enabled" value="1" checked style="width:auto"> Enabled</label>
        <button type="submit">Create Rule</button>
    </form>
</div>

<div class="panel">
    <h2>Existing Rules</h2>
    <table>
        <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Severity</th><th>Cooldown</th><th>Webhook</th><th>Update</th></tr></thead>
        <tbody>
            @forelse ($rules as $rule)
                <tr>
                    <td>{{ $rule->name }}</td>
                    <td>{{ $rule->type }}</td>
                    <td>{{ $rule->enabled ? 'enabled' : 'disabled' }}</td>
                    <td>{{ $rule->severity }}</td>
                    <td>{{ $rule->cooldown_minutes }} minutes</td>
                    <td>{{ $rule->webhook_url ? 'configured' : 'not configured' }}</td>
                    <td>
                        <form method="POST" action="/admin/ops-alert-rules/{{ $rule->id }}">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $rule->name }}" required>
                            <input type="hidden" name="type" value="ops_health">
                            <select name="severity">
                                <option value="warning" @selected($rule->severity === 'warning')>warning</option>
                                <option value="critical" @selected($rule->severity === 'critical')>critical</option>
                            </select>
                            <input type="number" name="cooldown_minutes" min="1" max="1440" value="{{ $rule->cooldown_minutes }}" required>
                            <input name="webhook_url" placeholder="Leave blank to keep existing URL">
                            <input name="webhook_secret" placeholder="Leave blank to keep existing secret">
                            <label><input type="checkbox" name="enabled" value="1" @checked($rule->enabled) style="width:auto"> Enabled</label>
                            <button type="submit">Save</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No ops alert rules yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $rules->links() }}
</div>
@endsection
