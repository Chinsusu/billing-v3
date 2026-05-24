@extends('layouts.app', ['title' => 'Invoices'])
@section('content')
<div class="panel">
    <h1>Invoices</h1>
    <table>
        <thead><tr><th>Invoice</th><th>Status</th><th>Total</th><th>Due</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->status }}</td>
                    <td>{{ number_format($invoice->total_amount) }} {{ $invoice->currency }}</td>
                    <td>{{ $invoice->due_at?->format('Y-m-d') ?? '-' }}</td>
                    <td><a href="/invoices/{{ $invoice->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $invoices->links() }}
</div>
@endsection
