@extends('layouts.admin', ['title' => 'Edit Role'])
@section('content')
<div class="panel">
    <h1>Edit Role</h1>
    <p><a href="/admin/roles">Back to Roles</a></p>
    <p><strong>{{ $role->name }}</strong></p>
    <form method="POST" action="/admin/roles/{{ $role->id }}">
        @csrf
        @method('PUT')

        <h2>Permissions</h2>
        <div class="grid">
            @foreach ($permissions as $permission)
                <label style="font-weight:400"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" style="width:auto" @checked(in_array($permission->name, old('permissions', $assignedPermissions), true))> {{ $permission->name }}</label>
            @endforeach
        </div>

        <button type="submit">Save Role</button>
    </form>
</div>
@endsection
