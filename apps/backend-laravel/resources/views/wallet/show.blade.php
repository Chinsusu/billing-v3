@extends('layouts.app', ['title' => 'Wallet'])
@section('content')
<div class="panel">
    <h1>Wallet</h1>
    <p><strong>{{ number_format($wallet->balance_amount) }} {{ $wallet->currency }}</strong></p>
    <form method="POST" action="/wallet/top-ups">
        @csrf
        <label for="amount">Top-up amount</label>
        <input id="amount" name="amount" type="number" min="1000" value="100000" required>
        <input name="currency" type="hidden" value="{{ $wallet->currency }}">
        <button type="submit">Create QR top-up</button>
    </form>
</div>

<div class="panel">
    <h2>Ledger</h2>
    <table>
        <thead>
            <tr>
                <th>Created</th>
                <th>Direction</th>
                <th>Amount</th>
                <th>Balance</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ledgerEntries as $entry)
                <tr>
                    <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $entry->direction }}</td>
                    <td>{{ number_format($entry->amount) }} {{ $entry->currency }}</td>
                    <td>{{ number_format($entry->balance_after) }} {{ $entry->currency }}</td>
                    <td>{{ $entry->description }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No ledger entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
