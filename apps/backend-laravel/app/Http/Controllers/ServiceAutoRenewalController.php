<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceAutoRenewalController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if ($service->status !== 'active') {
            throw ValidationException::withMessages(['service' => 'Only active services can use auto-renew.']);
        }

        $enabled = (bool) $validated['enabled'];
        $service->forceFill(['auto_renew_enabled' => $enabled])->save();

        return redirect("/services/{$service->id}")
            ->with('status', $enabled ? 'Auto-renew enabled.' : 'Auto-renew disabled.');
    }
}
