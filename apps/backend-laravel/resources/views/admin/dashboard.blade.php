@extends('layouts.app', ['title' => 'Admin Dashboard'])
@section('content')
<div class="panel">
    <h1>Admin Dashboard</h1>
    <div class="grid">
        <div class="panel"><strong>{{ $productCount }}</strong><br>Products</div>
        <div class="panel"><strong>{{ $activeProductCount }}</strong><br>Active products</div>
    </div>
    <p><a class="button" href="/admin/products">Manage Products</a></p>
</div>
@endsection
