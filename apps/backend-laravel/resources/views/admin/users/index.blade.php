@extends('layouts.app', ['title' => 'Admin Users'])
@section('content')
<div class="panel">
    <h1>Users & Roles</h1>
    <p>
        @can('users.manage')
            <a class="button" href="/admin/users/create">Create User</a>
        @endcan
        <a class="button secondary" href="/admin/roles">Roles</a>
    </p>
    <form method="GET" action="/admin/users">
        <div class="grid">
            <label>Search
                <input name="search" value="{{ $filters['search'] }}" placeholder="Email or name">
            </label>
            <label>Role
                <select name="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected($filters['role'] === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <button type="submit">Filter</button>
        <a href="/admin/users">Clear</a>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Direct Permissions</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->roles->pluck('name')->sort()->join(', ') ?: '-' }}</td>
                    <td>{{ $user->permissions->pluck('name')->sort()->join(', ') ?: '-' }}</td>
                    <td>
                        <a href="/admin/users/{{ $user->id }}">View</a>
                        @can('users.manage')
                            | <a href="/admin/users/{{ $user->id }}/edit">Edit</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
