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
}
