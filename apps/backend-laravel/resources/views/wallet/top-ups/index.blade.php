@extends('layouts.app', ['title' => 'Wallet Top-ups'])
@section('content')
<div class="panel">
    <h1>Wallet Top-ups</h1>
    <p><a class="button" href="/wallet">Create Top-up</a></p>
    <table>
        <thead><tr><th>Reference</th><th>Status</th><th>Amount</th><th>Expires</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($paymentIntents as $paymentIntent)
                <tr>
                    <td>{{ $paymentIntent->reference }}</td>
                    <td>{{ $paymentIntent->status }}</td>
                    <td>{{ number_format($paymentIntent->amount) }} {{ $paymentIntent->currency }}</td>
                    <td>{{ $paymentIntent->expires_at?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td><a href="/wallet/top-ups/{{ $paymentIntent->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No wallet top-ups yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $paymentIntents->links() }}
</div>
@endsection
