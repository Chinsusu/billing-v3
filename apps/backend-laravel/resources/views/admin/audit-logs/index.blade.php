@extends('layouts.admin', ['title' => 'Audit Logs'])
@section('content')
<div class="panel">
    <h1>Audit Logs</h1>
    <form method="GET" action="/admin/audit-logs">
        <label>Actor<input name="actor" value="{{ $filters['actor'] ?? '' }}"></label>
        <label>Action<input name="action" value="{{ $filters['action'] ?? '' }}"></label>
        <label>Subject Type<input name="auditable_type" value="{{ $filters['auditable_type'] ?? '' }}"></label>
        <label>Subject ID<input name="auditable_id" value="{{ $filters['auditable_id'] ?? '' }}"></label>
        <button type="submit">Filter</button>
        <a href="/admin/audit-logs">Clear</a>
    </form>
    <table>
        <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Subject</th><th>Route</th><th></th></tr></thead>
        <tbody>
            @forelse ($auditLogs as $log)
                <tr>
                    <td>{{ $log->created_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $log->actor_email ?? '-' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ class_basename($log->auditable_type) }} {{ $log->auditable_label ? ' / '.$log->auditable_label : '' }}</td>
                    <td>{{ $log->route_name ?? '-' }}</td>
                    <td><a href="/admin/audit-logs/{{ $log->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No audit logs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $auditLogs->links() }}
</div>
@endsection
