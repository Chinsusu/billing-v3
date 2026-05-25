<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', [
            'services' => Service::with('user')->latest()->paginate(20),
        ]);
    }

    public function show(Service $service): View
    {
        return view('admin.services.show', [
            'service' => $service->load([
                'user',
                'order',
                'orderItem',
                'product',
                'provisioningJobs' => fn ($query) => $query->latest(),
                'providerActionJobs' => fn ($query) => $query->latest(),
                'providerCallbackEvents' => fn ($query) => $query->latest(),
                'provisioningExecutionLogs' => fn ($query) => $query->latest(),
                'cancellations' => fn ($query) => $query->latest(),
                'autoRenewalAttempts' => fn ($query) => $query->latest(),
            ]),
        ]);
    }
}
