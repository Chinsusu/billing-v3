<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceProviderCancelController extends Controller
{
    public function __invoke(
        Request $request,
        Service $service,
        ProviderServiceActionService $providerActions,
        ProviderActionJobDispatcher $providerActionJobs,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! $providerActions->hasConfiguredAction($service, 'cancel')) {
            return redirect('/admin/services')->withErrors(['provider' => 'Provider cancel path is not configured.']);
        }

        $job = $providerActionJobs->enqueue($service, 'cancel', "service-cancel:{$service->id}:".now()->toISOString(), [
            'cancelled_at' => now()->toISOString(),
        ]);
        $audit->record($request->user(), 'provider_cancel_queued', $service, [], [], [
            'provider_action_job_id' => $job->id,
            'service_id' => $service->id,
            'user_id' => $service->user_id,
        ], $request, $service->product_name);

        return redirect('/admin/services')->with('status', 'Provider cancel queued.');
    }
}
