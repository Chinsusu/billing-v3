<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Provisioning\ProviderServiceActionService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ServiceProviderSyncController extends Controller
{
    public function __invoke(Service $service, ProviderServiceActionService $providerActions): RedirectResponse
    {
        try {
            $result = $providerActions->execute($service, 'sync', "service-sync:{$service->id}:".now()->toISOString());
        } catch (RuntimeException $exception) {
            return redirect('/admin/services')->withErrors(['provider' => $exception->getMessage()]);
        }

        if ($result === null) {
            return redirect('/admin/services')->withErrors(['provider' => 'Provider sync path is not configured.']);
        }

        $updates = [];
        if ($result->status !== null) {
            $updates['status'] = $result->status;
        }
        if ($result->expiresAt !== null) {
            $updates['expires_at'] = $result->expiresAt;
        }

        if ($updates !== []) {
            $service->forceFill($updates)->save();
        }

        return redirect('/admin/services')->with('status', 'Service synced from provider.');
    }
}
