@extends('layouts.admin', ['title' => 'Audit Log Detail'])
@section('content')
<div class="panel">
    <h1>Audit Log Detail</h1>
    <p><a href="/admin/audit-logs">Back to Audit Logs</a></p>
    <table>
        <tbody>
            <tr><th>Time</th><td>{{ $auditLog->created_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Actor</th><td>{{ $auditLog->actor_email ?? '-' }}</td></tr>
            <tr><th>Action</th><td>{{ $auditLog->action }}</td></tr>
            <tr><th>Subject</th><td>{{ $auditLog->auditable_type }} / {{ $auditLog->auditable_id }} / {{ $auditLog->auditable_label }}</td></tr>
            <tr><th>Route</th><td>{{ $auditLog->route_name ?? '-' }}</td></tr>
            <tr><th>IP</th><td>{{ $auditLog->ip_address ?? '-' }}</td></tr>
            <tr><th>User Agent</th><td>{{ $auditLog->user_agent ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>
<div class="panel">
    <h2>Before</h2>
    <pre>{{ json_encode($auditLog->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
<div class="panel">
    <h2>After</h2>
    <pre>{{ json_encode($auditLog->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
<div class="panel">
    <h2>Metadata</h2>
    <pre>{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
@endsection
