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
        ]);
    }

    public function show(AdminAuditLog $adminAuditLog): View
    {
        return view('admin.audit-logs.show', [
            'auditLog' => $adminAuditLog->load('actor'),
        ]);
    }
}
