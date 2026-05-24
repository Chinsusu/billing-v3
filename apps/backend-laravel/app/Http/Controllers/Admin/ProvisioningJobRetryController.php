<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningJob;
use App\Services\Provisioning\ProvisioningJobRetryService;
use Illuminate\Http\RedirectResponse;

class ProvisioningJobRetryController extends Controller
{
    public function __invoke(ProvisioningJob $provisioningJob, ProvisioningJobRetryService $retryService): RedirectResponse
    {
        $retryService->retry($provisioningJob);

        return redirect('/admin/provisioning-jobs')->with('status', 'Provisioning job queued for retry.');
    }
}
