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
}
