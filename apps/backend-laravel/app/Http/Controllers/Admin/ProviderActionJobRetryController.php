<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderActionJob;
use Illuminate\Http\RedirectResponse;

class ProviderActionJobRetryController extends Controller
{
    public function __invoke(ProviderActionJob $providerActionJob): RedirectResponse
    {
        if ($providerActionJob->status !== 'failed') {
            return redirect('/admin/provider-action-jobs')->withErrors(['provider_action_job' => 'Only failed provider action jobs can be retried.']);
        }

        $providerActionJob->forceFill([
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => null,
            'processed_at' => null,
            'last_error' => null,
        ])->save();

        return redirect('/admin/provider-action-jobs')->with('status', 'Provider action job queued for retry.');
    }
}
