@extends('layouts.admin', ['title' => 'Reseller Price Overrides'])

@section('content')
<div class="panel">
    <h1>Reseller Price Overrides</h1>
    <form method="POST" action="/admin/reseller-price-overrides">
        @csrf
        <div class="grid">
            <div>
                <label for="reseller_id">Reseller</label>
                <select id="reseller_id" name="reseller_id" required>
                    @foreach ($resellers as $reseller)
                        <option value="{{ $reseller->id }}">{{ $reseller->email }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="product_id">Product</label>
                <select id="product_id" name="product_id" required>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->code }} - {{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="price_amount">Price</label>
                <input id="price_amount" name="price_amount" type="number" min="0" required>
            </div>
        </div>
        <button type="submit">Save Override</button>
    </form>
</div>

<div class="panel">
    <h2>Overrides</h2>
    <table>
        <thead><tr><th>Reseller</th><th>Product</th><th>Price</th></tr></thead>
        <tbody>
            @forelse ($overrides as $override)
                <tr>
                    <td>{{ $override->reseller?->email ?? '-' }}</td>
                    <td>{{ $override->product?->code ?? '-' }}</td>
                    <td>{{ number_format($override->price_amount) }} {{ $override->product?->currency ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No overrides yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $overrides->links() }}
</div>
@endsection
