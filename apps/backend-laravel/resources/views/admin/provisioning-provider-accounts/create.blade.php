@extends('layouts.admin', ['title' => 'Create Provider Account'])
@section('content')
<div class="panel">
    <h1>Create Provider Account</h1>
    <form method="POST" action="/admin/provisioning-provider-accounts">
        @include('admin.provisioning-provider-accounts._form')
    </form>
</div>
@endsection
