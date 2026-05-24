<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProvisioningJob;
use Illuminate\View\View;

class ProvisioningJobController extends Controller
{
    public function index(): View
    {
        return view('admin.provisioning-jobs.index', [
            'provisioningJobs' => ProvisioningJob::with('user', 'service')->latest()->paginate(20),
        ]);
    }

    public function show(ProvisioningJob $provisioningJob): View
    {
        $provisioningJob->load([
            'service',
            'user',
            'executionLogs' => fn ($query) => $query->latest(),
            'executionLogs.providerAccount',
        ]);

        return view('admin.provisioning-jobs.show', [
            'provisioningJob' => $provisioningJob,
        ]);
    }
}
