<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsAlertEvent;
use Illuminate\Http\RedirectResponse;

class OpsAlertEventAcknowledgeController extends Controller
{
    public function __invoke(OpsAlertEvent $opsAlertEvent): RedirectResponse
    {
        $opsAlertEvent->forceFill([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by_id' => auth()->id(),
        ])->save();

        return redirect('/admin/ops-alert-events')->with('status', 'Ops alert acknowledged.');
    }
}
