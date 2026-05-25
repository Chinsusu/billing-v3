@extends('layouts.app', ['title' => 'Create Role'])
@section('content')
<div class="panel">
    <h1>Create Role</h1>
    <p><a href="/admin/roles">Back to Roles</a></p>
    <form method="POST" action="/admin/roles">
        @csrf
        <label>Role Name<input name="name" value="{{ old('name') }}" placeholder="renewal_operator" required></label>
        <p class="muted">Use lowercase letters, digits, dot, underscore, or hyphen.</p>

        <h2>Permissions</h2>
        <div class="grid">
            @foreach ($permissions as $permission)
                <label style="font-weight:400"><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" style="width:auto" @checked(in_array($permission->name, old('permissions', []), true))> {{ $permission->name }}</label>
            @endforeach
        </div>

        <button type="submit">Create Role</button>
    </form>
</div>
@endsection
