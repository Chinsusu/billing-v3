<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlertRule;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpsAlertRuleController extends Controller
{
    private const AUDIT_FIELDS = ['name', 'type', 'enabled', 'severity', 'cooldown_minutes', 'webhook_url', 'webhook_secret'];

    public function index(): View
    {
        return view('admin.ops-alert-rules.index', [
            'rules' => OpsAlertRule::latest()->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $rule = OpsAlertRule::create($this->attributes($request));
        $audit->record($request->user(), 'created', $rule, [], $audit->snapshot($rule, self::AUDIT_FIELDS), [], $request);

        return redirect('/admin/ops-alert-rules')->with('status', 'Ops alert rule created.');
    }

    public function update(Request $request, OpsAlertRule $opsAlertRule, AuditLogger $audit): RedirectResponse
    {
        $before = $audit->snapshot($opsAlertRule, self::AUDIT_FIELDS);
        $opsAlertRule->update($this->attributes($request, $opsAlertRule));
        $opsAlertRule->refresh();
        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($opsAlertRule, self::AUDIT_FIELDS));
        $audit->record($request->user(), 'updated', $opsAlertRule, $beforeChanges, $afterChanges, [], $request);

        return redirect('/admin/ops-alert-rules')->with('status', 'Ops alert rule updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, ?OpsAlertRule $existing = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:ops_health'],
            'enabled' => ['nullable', 'boolean'],
            'severity' => ['required', 'in:warning,critical'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'webhook_secret' => ['nullable', 'string', 'max:2048'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'enabled' => $request->boolean('enabled'),
            'severity' => $validated['severity'],
            'cooldown_minutes' => (int) $validated['cooldown_minutes'],
        ];

        if ($existing === null || filled($validated['webhook_url'] ?? null)) {
            $attributes['webhook_url'] = $validated['webhook_url'] ?? null;
        }

        if ($existing === null || filled($validated['webhook_secret'] ?? null)) {
            $attributes['webhook_secret'] = $validated['webhook_secret'] ?? null;
        }

        return $attributes;
    }
}
