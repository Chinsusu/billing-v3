@extends('layouts.admin', ['title' => 'Edit Bank Integration'])
@section('content')
<div class="panel">
    <h1>Edit Bank Integration</h1>
    <form method="POST" action="/admin/bank-integrations/{{ $bankIntegration->id }}">
        @method('PUT')
        @include('admin.bank-integrations._form')
    </form>
</div>
@endsection
