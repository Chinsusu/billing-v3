<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceProviderSyncController extends Controller
{
    public function __invoke(
        Request $request,
        Service $service,
        ProviderServiceActionService $providerActions,
        ProviderActionJobDispatcher $providerActionJobs,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! $providerActions->hasConfiguredAction($service, 'sync')) {
            return redirect('/admin/services')->withErrors(['provider' => 'Provider sync path is not configured.']);
        }

        $job = $providerActionJobs->enqueue($service, 'sync', "service-sync:{$service->id}:".now()->toISOString());
        $audit->record($request->user(), 'provider_sync_queued', $service, [], [], [
            'provider_action_job_id' => $job->id,
            'service_id' => $service->id,
            'user_id' => $service->user_id,
        ], $request, $service->product_name);

        return redirect('/admin/services')->with('status', 'Provider sync queued.');
    }
}
