<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationEventController extends Controller
{
    public function index(Request $request): View
    {
        $query = NotificationEvent::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('recipient')) {
            $query->where('recipient_email', 'like', '%'.$request->string('recipient')->toString().'%');
        }

        return view('admin.notification-events.index', [
            'notificationEvents' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'type', 'recipient']),
            'filterOptions' => [
                'statuses' => NotificationEvent::query()
                    ->whereNotNull('status')
                    ->distinct()
                    ->orderBy('status')
                    ->limit(40)
                    ->pluck('status')
                    ->all(),
                'types' => NotificationEvent::query()
                    ->whereNotNull('type')
                    ->distinct()
                    ->orderBy('type')
                    ->limit(80)
                    ->pluck('type')
                    ->all(),
                'recipients' => NotificationEvent::query()
                    ->whereNotNull('recipient_email')
                    ->where('recipient_email', '!=', '')
                    ->distinct()
                    ->orderBy('recipient_email')
                    ->limit(100)
                    ->pluck('recipient_email')
                    ->all(),
            ],
        ]);
    }

    public function show(NotificationEvent $notificationEvent): View
    {
        return view('admin.notification-events.show', [
            'notificationEvent' => $notificationEvent->load('user'),
        ]);
    }
}
