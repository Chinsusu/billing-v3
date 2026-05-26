<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalance;
use App\Models\Service;
use App\Services\Services\ServiceRenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceRenewalController extends Controller
{
    public function __invoke(Request $request, Service $service, ServiceRenewalService $renewalService): RedirectResponse
    {
        try {
            $renewalService->renew($service, $request->user());
        } catch (InsufficientWalletBalance $exception) {
            return redirect("/services/{$service->id}")->withErrors(['wallet' => $exception->getMessage()]);
        }

        return redirect("/services/{$service->id}")->with('status', 'Service renewed.');
    }
}
