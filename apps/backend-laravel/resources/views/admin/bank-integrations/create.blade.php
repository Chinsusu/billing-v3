@extends('layouts.app', ['title' => 'Create Bank Integration'])
@section('content')
<div class="panel">
    <h1>Create Bank Integration</h1>
    <form method="POST" action="/admin/bank-integrations">
        @include('admin.bank-integrations._form')
    </form>
</div>
@endsection
