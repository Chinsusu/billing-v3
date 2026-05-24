@extends('layouts.app', ['title' => 'Payment Events'])
@section('content')
<div class="panel">
    <h1>Payment Events</h1>
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
                    <td>{{ $event->provider_transaction_id }}</td>
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
