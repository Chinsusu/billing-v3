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
        ]);
    }

    public function show(NotificationEvent $notificationEvent): View
    {
        return view('admin.notification-events.show', [
            'notificationEvent' => $notificationEvent->load('user'),
        ]);
    }
}
