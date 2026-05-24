<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        return view('services.index', [
            'services' => Service::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function show(Request $request, Service $service): View
    {
        abort_if($service->user_id !== $request->user()->id, Response::HTTP_NOT_FOUND);

        return view('services.show', [
            'service' => $service->load([
                'order',
                'product',
                'provisioningJobs' => fn ($query) => $query->latest(),
                'providerActionJobs' => fn ($query) => $query->latest(),
                'provisioningExecutionLogs' => fn ($query) => $query->latest(),
                'cancellations' => fn ($query) => $query->latest(),
            ]),
        ]);
    }
}
