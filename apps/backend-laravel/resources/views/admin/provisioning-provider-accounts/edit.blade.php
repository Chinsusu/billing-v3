@extends('layouts.app', ['title' => 'Edit Provider Account'])
@section('content')
<div class="panel">
    <h1>Edit Provider Account</h1>
    <form method="POST" action="/admin/provisioning-provider-accounts/{{ $providerAccount->id }}">
        @method('PUT')
        @include('admin.provisioning-provider-accounts._form')
    </form>
</div>
@endsection
