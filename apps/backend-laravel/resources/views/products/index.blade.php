@extends('layouts.app', ['title' => 'Products'])
@section('content')
<div class="panel">
    <h1>Products</h1>
    @forelse ($products as $product)
        <div class="panel">
            <h2>{{ $product->name }}</h2>
            <p class="muted">{{ strtoupper($product->type) }} · {{ number_format($product->price_amount) }} {{ $product->currency }} · {{ $product->duration_days }} days</p>
            <p>{{ $product->description }}</p>
        </div>
    @empty
        <p>No active products.</p>
    @endforelse
</div>
@endsection
