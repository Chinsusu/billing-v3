<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        return view('services.index', [
            'services' => Service::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }
}
