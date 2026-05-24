<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationEvent;
use Illuminate\Http\RedirectResponse;

class NotificationEventRetryController extends Controller
{
    public function __invoke(NotificationEvent $notificationEvent): RedirectResponse
    {
        if ($notificationEvent->status === 'failed') {
            $notificationEvent->forceFill([
                'status' => 'pending',
                'available_at' => now(),
                'last_error' => null,
            ])->save();
        }

        return redirect("/admin/notification-events/{$notificationEvent->id}")->with('status', 'Notification queued for retry.');
    }
}
