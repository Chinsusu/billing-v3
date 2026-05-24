<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.notification-templates.index', [
            'notificationTemplates' => NotificationTemplate::orderBy('type')->orderBy('channel')->get(),
        ]);
    }

    public function edit(NotificationTemplate $notificationTemplate): View
    {
        return view('admin.notification-templates.edit', [
            'notificationTemplate' => $notificationTemplate,
        ]);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject_template' => ['required', 'string', 'max:255'],
            'body_template' => ['required', 'string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $notificationTemplate->update($validated + [
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect('/admin/notification-templates')->with('status', 'Notification template updated.');
    }
}
