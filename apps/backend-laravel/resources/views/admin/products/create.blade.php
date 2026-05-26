@extends('layouts.admin', ['title' => 'Create Product'])
@section('content')
<div class="panel">
    <h1>Create Product</h1>
    <form method="POST" action="/admin/products">
        @include('admin.products._form')
    </form>
</div>
@endsection
