@extends('layouts.app', ['title' => 'Products'])
@section('content')
<x-page-header
    title="Products"
    subtitle="Choose an active service package and start checkout from your wallet."
/>

@if ($products->isEmpty())
    <div class="panel">
        <x-empty-state title="No active products." message="Products will appear here when an operator enables them." />
    </div>
@else
    <div class="product-grid">
    @foreach ($products as $product)
        <article class="product-card">
            <h2>{{ $product->name }}</h2>
            <div class="product-card__meta">
                <x-status-badge tone="primary">{{ strtoupper($product->type) }}</x-status-badge>
                <span>{{ $product->duration_days }} days</span>
            </div>
            <div class="product-card__price">{{ number_format($product->price_amount) }} {{ $product->currency }}</div>
            <p>{{ $product->description }}</p>
            <div class="product-card__actions">
            @auth
                <form method="POST" action="/products/{{ $product->id }}/order">
                    @csrf
                    <button type="submit">Order with wallet</button>
                </form>
            @else
                <a class="button" href="/login">Login to order</a>
            @endauth
            </div>
        </article>
    @endforeach
    </div>
@endif
@endsection
