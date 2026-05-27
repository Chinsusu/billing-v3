@extends('layouts.admin', ['title' => 'Payment Events'])
@section('content')
@php
    $paymentEventTableColumns = auth()->user()?->can('payment_events.update') ? 6 : 5;
@endphp

<div class="panel">
    <h1>Payment Events</h1>
    <form method="GET" action="/admin/payment-events">
        <div class="grid">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reference">Reference</label>
                <input id="reference" name="reference" value="{{ $filters['reference'] }}">
            </div>
        </div>
        <p>
            <button type="submit">Filter</button>
            <a class="button secondary" href="/admin/payment-events">Reset</a>
        </p>
    </form>
    <table>
        <thead>
            <tr>
                <th>Transaction</th>
                <th>Reference</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Processed</th>
                @can('payment_events.update')
                    <th>Actions</th>
                @endcan
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentEvents as $event)
                <tr>
                    <td><a href="/admin/payment-events/{{ $event->id }}">{{ $event->provider_transaction_id }}</a></td>
                    <td>{{ $event->reference }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->amount ? number_format($event->amount).' '.$event->currency : '-' }}</td>
                    <td>{{ $event->processed_at?->format('Y-m-d H:i') }}</td>
                    @can('payment_events.update')
                        <td>
                            @if ($event->provider === 'manual_admin')
                                <a href="/admin/payment-events/{{ $event->id }}/edit" class="button secondary button-soft button-compact">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.75 3.75 1.83-1.83a.996.996 0 0 0 0-1.41l-2.34-2.34c-.2-.2-.45-.29-.71-.29Zm-3.6 3.19L3 17.25V21h3.75L17.81 9.94l-3.75-3.75Z"/></svg>
                                    <span>Edit</span>
                                </a>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="{{ $paymentEventTableColumns }}" class="muted">No payment events yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $paymentEvents->links() }}
</div>
@endsection
