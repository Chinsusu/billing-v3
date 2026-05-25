<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceAutoRenewalAttempt;
use App\Services\Services\ServiceAutoRenewalOpsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAutoRenewalAttemptRetryController extends Controller
{
    public function __invoke(Request $request, ServiceAutoRenewalAttempt $serviceAutoRenewalAttempt, ServiceAutoRenewalOpsService $ops): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $result = $ops->retryNow($serviceAutoRenewalAttempt, $request->user(), $validated['reason'], $request);

        return redirect('/admin/renewals')->with('status', "Auto-renew retry result: {$result}.");
    }
}
