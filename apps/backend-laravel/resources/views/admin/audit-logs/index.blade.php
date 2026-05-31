@extends('layouts.admin', ['title' => 'Audit Logs'])
@section('content')
<x-page-header
    title="Audit Logs"
    eyebrow="Security"
/>

<div class="panel invoice-filter-panel audit-log-filter-panel">
    <form class="invoice-filter-form audit-log-filter-form" method="GET" action="/admin/audit-logs">
        <div class="invoice-filter-fields audit-log-filter-fields">
            <label class="invoice-filter-field audit-log-filter-field" for="actor">
                <span>Actor</span>
                <input id="actor" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Search actor" list="audit-log-actor-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field audit-log-filter-field" for="action">
                <span>Action</span>
                <input id="action" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Search action" list="audit-log-action-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field audit-log-filter-field" for="auditable_type">
                <span>Subject Type</span>
                <input id="auditable_type" name="auditable_type" value="{{ $filters['auditable_type'] ?? '' }}" placeholder="Search type" list="audit-log-subject-type-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field audit-log-filter-field" for="auditable_id">
                <span>Subject ID</span>
                <input id="auditable_id" name="auditable_id" value="{{ $filters['auditable_id'] ?? '' }}" placeholder="Search ID" list="audit-log-subject-id-options" autocomplete="off">
            </label>
        </div>
        <div class="invoice-filter-actions audit-log-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary button-soft" href="/admin/audit-logs">Clear</a>
        </div>

        <datalist id="audit-log-actor-options">
            @foreach ($filterOptions['actor_emails'] as $actorEmail)
                <option value="{{ $actorEmail }}"></option>
            @endforeach
        </datalist>
        <datalist id="audit-log-action-options">
            @foreach ($filterOptions['actions'] as $action)
                <option value="{{ $action }}"></option>
            @endforeach
        </datalist>
        <datalist id="audit-log-subject-type-options">
            @foreach ($filterOptions['auditable_types'] as $auditableType)
                <option value="{{ $auditableType }}"></option>
            @endforeach
        </datalist>
        <datalist id="audit-log-subject-id-options">
            @foreach ($filterOptions['auditable_subjects'] as $auditableSubject)
                <option value="{{ $auditableSubject['value'] }}" label="{{ $auditableSubject['label'] }}"></option>
            @endforeach
        </datalist>
    </form>
</div>

<div class="panel" data-audit-log-index>
    <div class="panel-heading">
        <div>
            <h2>Audit Trail</h2>
            <p class="muted">{{ number_format($auditLogs->total()) }} records found.</p>
        </div>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>Route</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $log)
                    @php
                        $actionTone = match ($log->action) {
                            'created', 'enabled', 'user_enabled', 'wallet_adjusted', 'admin_order_created' => 'success',
                            'deleted', 'archived', 'disabled', 'user_disabled' => 'danger',
                            default => str_contains($log->action, 'failed') ? 'danger' : 'primary',
                        };
                        $subjectType = $log->auditable_type ? class_basename($log->auditable_type) : '-';
                    @endphp
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                        <td>
                            <div class="audit-log-actor">
                                <strong>{{ $log->actor_email ?? 'System' }}</strong>
                            </div>
                        </td>
                        <td class="audit-log-row__action">
                            <x-status-badge :tone="$actionTone">{{ $log->action }}</x-status-badge>
                        </td>
                        <td>
                            <div class="audit-log-subject">
                                <strong>{{ $subjectType }}</strong>
                                <span>{{ $log->auditable_label ?: ($log->auditable_id ?: '-') }}</span>
                            </div>
                        </td>
                        <td><span class="audit-log-route">{{ $log->route_name ?? '-' }}</span></td>
                        <td>
                            <a class="button secondary button-soft button-compact" href="/admin/audit-logs/{{ $log->id }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No audit logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin-pagination :paginator="$auditLogs" name="audit-logs" />
</div>
@endsection
