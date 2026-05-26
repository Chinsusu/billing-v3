<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceAutoRenewalOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAutoRenewalAdminToggleController extends Controller
{
    public function __invoke(Request $request, Service $service, ServiceAutoRenewalOpsService $ops): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $ops->toggleService($service, $enabled, $request->user(), $validated['reason'], $request);

        return redirect("/admin/services/{$service->id}")
            ->with('status', $enabled ? 'Auto-renew enabled for service.' : 'Auto-renew disabled for service.');
    }
}
