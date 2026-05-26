@extends('layouts.admin', ['title' => 'Create Product'])
@section('content')
<x-page-header
    title="Create Product"
    subtitle="Define the customer-facing plan, pricing, lifecycle policy, and provisioning provider mapping."
    eyebrow="Resources"
>
    <x-slot:actions>
        <a class="button secondary button-soft" href="/admin/products">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z"/></svg>
            <span>Back to Products</span>
        </a>
    </x-slot:actions>
</x-page-header>

<div class="panel product-form-panel">
    <form class="product-form-shell" method="POST" action="/admin/products">
        @include('admin.products._form')
    </form>
</div>
@endsection
