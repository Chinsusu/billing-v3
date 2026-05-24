@extends('layouts.app', ['title' => 'Admin Invoices'])
@section('content')
<div class="panel">
    <h1>Invoices</h1>
    <form method="POST" action="/admin/invoices">
        @csrf
        <label for="user_email">Customer email</label>
        <input id="user_email" name="user_email" type="email" required>
        <label for="total_amount">Total amount</label>
        <input id="total_amount" name="total_amount" type="number" min="1" required>
        <input name="currency" type="hidden" value="VND">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" required></textarea>
        <button type="submit">Create invoice</button>
    </form>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->user->email }}</td>
                    <td>{{ $invoice->status }}</td>
                    <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $invoices->links() }}
</div>
@endsection
