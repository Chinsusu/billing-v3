<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationPreferenceController extends Controller
{
    public function edit(
        Request $request,
        NotificationTemplateCatalog $catalog,
        NotificationPreferenceService $preferences,
    ): View {
        return view('notification-preferences.edit', [
            'notificationTypes' => $catalog->customerTypes(),
            'states' => $preferences->statesFor($request->user()),
        ]);
    }

    public function update(
        Request $request,
        NotificationTemplateCatalog $catalog,
        NotificationPreferenceService $preferences,
    ): RedirectResponse {
        $validated = $request->validate([
            'enabled_types' => ['array'],
            'enabled_types.*' => ['string', Rule::in(array_keys($catalog->customerTypes()))],
        ]);

        $preferences->sync($request->user(), $validated['enabled_types'] ?? []);

        return redirect('/notification-preferences')->with('status', 'Notification preferences updated.');
    }
}
