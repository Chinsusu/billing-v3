<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Provisioning\ProviderActionJobDispatcher;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;

class ServiceProviderCancelController extends Controller
{
    public function __invoke(
        Service $service,
        ProviderServiceActionService $providerActions,
        ProviderActionJobDispatcher $providerActionJobs,
    ): RedirectResponse {
        if (! $providerActions->hasConfiguredAction($service, 'cancel')) {
            return redirect('/admin/services')->withErrors(['provider' => 'Provider cancel path is not configured.']);
        }

        $providerActionJobs->enqueue($service, 'cancel', "service-cancel:{$service->id}:".now()->toISOString(), [
            'cancelled_at' => now()->toISOString(),
        ]);

        return redirect('/admin/services')->with('status', 'Provider cancel queued.');
    }
}
