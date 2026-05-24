<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderActionJob;
use Illuminate\View\View;

class ProviderActionJobController extends Controller
{
    public function index(): View
    {
        return view('admin.provider-action-jobs.index', [
            'providerActionJobs' => ProviderActionJob::with('user', 'service', 'providerAccount')->latest()->paginate(20),
        ]);
    }
}
