<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        return view('support.tickets.index', [
            'tickets' => SupportTicket::where('user_id', $request->user()->id)
                ->latest('last_activity_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('support.tickets.create');
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($auditLogger, $request, $validated): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id,
                'opened_by_id' => $request->user()->id,
                'subject' => $validated['subject'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'last_activity_at' => now(),
            ]);

            $ticket->notes()->create([
                'author_id' => $request->user()->id,
                'visibility' => 'customer',
                'body' => $validated['body'],
            ]);

            $auditLogger->record($request->user(), 'support_ticket_created', $ticket, [], [
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
            ], [], $request, $ticket->subject);

            return $ticket;
        });

        return redirect("/support/tickets/{$ticket->id}")->with('status', 'Support ticket opened.');
    }

    public function show(SupportTicket $ticket, Request $request): View
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return view('support.tickets.show', [
            'ticket' => $ticket->load(['notes' => fn ($query) => $query->where('visibility', 'customer')->latest(), 'notes.author']),
        ]);
    }

    public function note(SupportTicket $ticket, Request $request): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->notes()->create([
            'author_id' => $request->user()->id,
            'visibility' => 'customer',
            'body' => $validated['body'],
        ]);
        $ticket->forceFill(['last_activity_at' => now()])->save();

        return redirect("/support/tickets/{$ticket->id}")->with('status', 'Reply added.');
    }
}
