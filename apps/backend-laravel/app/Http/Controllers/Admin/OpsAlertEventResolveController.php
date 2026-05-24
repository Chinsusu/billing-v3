<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlertEvent;
use Illuminate\Http\RedirectResponse;

class OpsAlertEventResolveController extends Controller
{
    public function __invoke(OpsAlertEvent $opsAlertEvent): RedirectResponse
    {
        $opsAlertEvent->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_id' => auth()->id(),
        ])->save();

        return redirect('/admin/ops-alert-events')->with('status', 'Ops alert resolved.');
    }
}
