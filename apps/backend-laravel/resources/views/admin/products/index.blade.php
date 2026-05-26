@extends('layouts.app', ['title' => 'Admin Products'])
@section('content')
<div class="panel">
    <h1>Products</h1>
    <p><a class="button" href="/admin/products/create">Create Product</a></p>
    <table>
        <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Status</th><th>Price</th><th>Provider</th><th></th></tr></thead>
        <tbody>
        @foreach ($products as $product)
            <tr>
                <td>{{ $product->code }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->type }}</td>
                <td>{{ $product->status }}</td>
                <td>{{ number_format($product->price_amount) }} {{ $product->currency }}</td>
                <td>{{ $product->providerAccount?->slug ?? 'sandbox' }}{{ $product->provider_plan_code ? ' / '.$product->provider_plan_code : '' }}</td>
                <td>
                    <a class="button secondary" href="/admin/products/{{ $product->id }}/edit">Edit</a>
                    <form method="POST" action="/admin/products/{{ $product->id }}" style="display:inline">@csrf @method('DELETE')<button class="button danger" type="submit">Archive</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $products->links() }}
</div>
@endsection
