<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));

        return view('admin.support-tickets.index', [
            'tickets' => SupportTicket::with(['user', 'assignedTo'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest('last_activity_at')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support-tickets.show', [
            'ticket' => $ticket->load(['user', 'assignedTo', 'notes.author']),
            'operators' => User::permission('support_tickets.manage')->orderBy('email')->get(),
        ]);
    }

    public function update(SupportTicket $ticket, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,pending,resolved,closed'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'assigned_to_id' => ['nullable', Rule::exists('users', 'id')],
        ]);
        $before = [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to_id' => $ticket->assigned_to_id,
        ];

        $ticket->forceFill([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'assigned_to_id' => $validated['assigned_to_id'] ?? null,
            'last_activity_at' => now(),
        ])->save();

        $auditLogger->record($request->user(), 'support_ticket_updated', $ticket, $before, [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to_id' => $ticket->assigned_to_id,
        ], [], $request, $ticket->subject);

        return redirect("/admin/support-tickets/{$ticket->id}")->with('status', 'Support ticket updated.');
    }

    public function note(SupportTicket $ticket, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visibility' => ['required', 'in:customer,internal'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->notes()->create([
            'author_id' => $request->user()->id,
            'visibility' => $validated['visibility'],
            'body' => $validated['body'],
        ]);
        $ticket->forceFill(['last_activity_at' => now()])->save();

        return redirect("/admin/support-tickets/{$ticket->id}")->with('status', 'Note added.');
    }
}
