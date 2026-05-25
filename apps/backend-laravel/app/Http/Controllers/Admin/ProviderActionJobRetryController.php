<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderActionJob;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderActionJobRetryController extends Controller
{
    private const AUDIT_FIELDS = ['action', 'status', 'attempts', 'max_attempts', 'available_at', 'processed_at', 'last_error'];

    public function __invoke(Request $request, ProviderActionJob $providerActionJob, AuditLogger $audit): RedirectResponse
    {
        if ($providerActionJob->status !== 'failed') {
            return redirect('/admin/provider-action-jobs')->withErrors(['provider_action_job' => 'Only failed provider action jobs can be retried.']);
        }

        $before = $audit->snapshot($providerActionJob, self::AUDIT_FIELDS);
        $providerActionJob->forceFill([
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => null,
            'processed_at' => null,
            'last_error' => null,
        ])->save();
        $providerActionJob->refresh();
        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($providerActionJob, self::AUDIT_FIELDS));
        $audit->record($request->user(), 'provider_action_retried', $providerActionJob, $beforeChanges, $afterChanges, [
            'service_id' => $providerActionJob->service_id,
            'user_id' => $providerActionJob->user_id,
        ], $request);

        return redirect('/admin/provider-action-jobs')->with('status', 'Provider action job queued for retry.');
    }
}
