@extends('layouts.app', ['title' => 'Edit User Authorization'])
@section('content')
<div class="panel">
    <h1>Edit User Authorization</h1>
    <p><a href="/admin/users/{{ $managedUser->id }}">Back to User</a></p>
    <p><strong>{{ $managedUser->email }}</strong></p>
    <form method="POST" action="/admin/users/{{ $managedUser->id }}">
        @csrf
        @method('PUT')

        <h2>Roles</h2>
        <div class="grid">
            @foreach ($roles as $role)
                <label style="font-weight:400"><input type="checkbox" name="roles[]" value="{{ $role->name }}" style="width:auto" @checked(in_array($role->name, old('roles', $assignedRoles), true))> {{ $role->name }}</label>
            @endforeach
        </div>

        <h2>Direct Permissions</h2>
        <div class="grid">
            @foreach ($permissions as $permission)
                <label style="font-weight:400"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" style="width:auto" @checked(in_array($permission->name, old('permissions', $assignedPermissions), true))> {{ $permission->name }}</label>
            @endforeach
        </div>

        <button type="submit">Save Authorization</button>
    </form>
</div>
@endsection
