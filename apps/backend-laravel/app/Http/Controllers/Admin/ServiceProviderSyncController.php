<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;

class ServiceProviderSyncController extends Controller
{
    public function __invoke(
        Service $service,
        ProviderServiceActionService $providerActions,
        ProviderActionJobDispatcher $providerActionJobs,
    ): RedirectResponse
    {
        if (!$providerActions->hasConfiguredAction($service, 'sync')) {
            return redirect('/admin/services')->withErrors(['provider' => 'Provider sync path is not configured.']);
        }

        $providerActionJobs->enqueue($service, 'sync', "service-sync:{$service->id}:".now()->toISOString());

        return redirect('/admin/services')->with('status', 'Provider sync queued.');
    }
}
