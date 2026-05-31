<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AdminAuditLog::with('actor')->latest('created_at');

        if ($request->filled('actor')) {
            $query->where('actor_email', 'like', '%'.$request->string('actor')->toString().'%');
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', 'like', '%'.$request->string('auditable_type')->toString().'%');
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->string('auditable_id')->toString());
        }

        return view('admin.audit-logs.index', [
            'auditLogs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['actor', 'action', 'auditable_type', 'auditable_id']),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function show(AdminAuditLog $adminAuditLog): View
    {
        return view('admin.audit-logs.show', [
            'auditLog' => $adminAuditLog->load('actor'),
        ]);
    }

    /**
     * @return array{
     *     actor_emails: array<int, string>,
     *     actions: array<int, string>,
     *     auditable_types: array<int, string>,
     *     auditable_subjects: array<int, array{value: string, label: string}
     * }
     */
    private function filterOptions(): array
    {
        $actorEmails = AdminAuditLog::query()
            ->whereNotNull('actor_email')
            ->where('actor_email', '!=', '')
            ->distinct()
            ->orderBy('actor_email')
            ->limit(60)
            ->pluck('actor_email')
            ->values()
            ->all();

        $actions = AdminAuditLog::query()
            ->whereNotNull('action')
            ->where('action', '!=', '')
            ->distinct()
            ->orderBy('action')
            ->limit(80)
            ->pluck('action')
            ->values()
            ->all();

        $auditableTypes = AdminAuditLog::query()
            ->whereNotNull('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->map(fn (string $type): string => class_basename($type))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $auditableSubjects = AdminAuditLog::query()
            ->whereNotNull('auditable_id')
            ->where('auditable_id', '!=', '')
            ->latest('created_at')
            ->limit(100)
            ->get(['auditable_id', 'auditable_label', 'auditable_type'])
            ->map(fn (AdminAuditLog $log): array => [
                'value' => (string) $log->auditable_id,
                'label' => $log->auditable_label ?: trim(class_basename((string) $log->auditable_type).' #'.$log->auditable_id),
            ])
            ->unique('value')
            ->take(60)
            ->values()
            ->all();

        return [
            'actor_emails' => $actorEmails,
            'actions' => $actions,
            'auditable_types' => $auditableTypes,
            'auditable_subjects' => $auditableSubjects,
        ];
    }
}
