@extends('layouts.app', ['title' => 'Create Admin User'])
@section('content')
<div class="panel">
    <h1>Create User</h1>
    <p><a href="/admin/users">Back to Users</a></p>
    <form method="POST" action="/admin/users">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required></label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required></label>
        <label>Password<input name="password" type="password" required></label>
        <label>Confirm Password<input name="password_confirmation" type="password" required></label>

        <h2>Roles</h2>
        <div class="grid">
            @foreach ($roles as $role)
                <label style="font-weight:400"><input type="checkbox" name="roles[]" value="{{ $role->name }}" style="width:auto" @checked(in_array($role->name, old('roles', []), true))> {{ $role->name }}</label>
            @endforeach
        </div>

        <h2>Direct Permissions</h2>
        <div class="grid">
            @foreach ($permissions as $permission)
                <label style="font-weight:400"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" style="width:auto" @checked(in_array($permission->name, old('permissions', []), true))> {{ $permission->name }}</label>
            @endforeach
        </div>

        <button type="submit">Create User</button>
    </form>
</div>
@endsection
