@extends('layouts.admin', ['title' => 'Payment Events'])
@section('content')
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
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No payment events yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $paymentEvents->links() }}
</div>
@endsection
