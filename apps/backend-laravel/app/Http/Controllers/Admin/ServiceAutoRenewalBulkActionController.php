<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Services\ServiceAutoRenewalOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceAutoRenewalBulkActionController extends Controller
{
    public function __invoke(Request $request, ServiceAutoRenewalOpsService $ops): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['retry', 'disable'])],
            'attempt_ids' => ['required', 'array', 'min:1'],
            'attempt_ids.*' => ['required', 'uuid', 'exists:service_auto_renewal_attempts,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $counts = $validated['action'] === 'retry'
            ? $ops->bulkRetry($validated['attempt_ids'], $request->user(), $validated['reason'], $request)
            : $ops->bulkDisable($validated['attempt_ids'], $request->user(), $validated['reason'], $request);

        $label = $validated['action'] === 'retry' ? 'Bulk retry' : 'Bulk disable';

        return redirect('/admin/renewals')->with('status', "{$label} applied={$counts['applied']} skipped={$counts['skipped']}.");
    }
}
