@extends('layouts.admin', ['title' => 'Edit Product'])
@section('content')
<div class="panel">
    <h1>Edit Product</h1>
    <form method="POST" action="/admin/products/{{ $product->id }}">
        @method('PUT')
        @include('admin.products._form')
    </form>
</div>
@endsection
