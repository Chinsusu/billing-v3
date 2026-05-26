@extends('layouts.admin', ['title' => 'Admin Invoices'])
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
    <h2>Filter</h2>
    <form method="GET" action="/admin/invoices">
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
                <label for="customer">Customer email</label>
                <input id="customer" name="customer" value="{{ $filters['customer'] }}">
            </div>
        </div>
        <p>
            <button type="submit">Filter</button>
            <a class="button secondary" href="/admin/invoices">Reset</a>
        </p>
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
                    <td><a href="/admin/invoices/{{ $invoice->id }}">{{ $invoice->invoice_number }}</a></td>
                    <td><a href="/admin/customers/{{ $invoice->user_id }}">{{ $invoice->user->email }}</a></td>
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
