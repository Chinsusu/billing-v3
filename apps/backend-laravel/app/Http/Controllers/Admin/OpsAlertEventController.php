<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlertEvent;
use Illuminate\View\View;

class OpsAlertEventController extends Controller
{
    public function index(): View
    {
        return view('admin.ops-alert-events.index', [
            'events' => OpsAlertEvent::with('rule')->latest('last_seen_at')->paginate(30),
        ]);
    }
}
