@extends('layouts.admin', ['title' => 'Admin Support Tickets'])
@section('content')
<x-page-header title="Support Tickets" eyebrow="Security" />

<div class="panel invoice-filter-panel support-ticket-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/support-tickets">
        <div class="invoice-filter-fields admin-filter-fields--1">
            <label class="invoice-filter-field" for="ticket-status">
                <span>Status</span>
                <select id="ticket-status" name="status">
                    <option value="">All statuses</option>
                    @foreach (['open', 'pending', 'resolved', 'closed'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
        </div>
    </form>
</div>
<div class="panel">
    <table>
        <thead><tr><th>Subject</th><th>Customer</th><th>Status</th><th>Priority</th><th>Assigned</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td><a href="/admin/support-tickets/{{ $ticket->id }}">{{ $ticket->subject }}</a></td>
                    <td>{{ $ticket->user?->email }}</td>
                    <td>{{ $ticket->status }}</td>
                    <td>{{ $ticket->priority }}</td>
                    <td>{{ $ticket->assignedTo?->email ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No support tickets.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $tickets->links() }}
</div>
@endsection
