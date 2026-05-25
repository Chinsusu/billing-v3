@extends('layouts.app', ['title' => 'Admin Roles'])
@section('content')
<div class="panel">
    <h1>Roles</h1>
    <p>
        <a class="button secondary" href="/admin/users">Users</a>
        @can('roles.manage')
            <a class="button" href="/admin/roles/create">Create Role</a>
        @endcan
    </p>
    <table>
        <thead><tr><th>Name</th><th>Users</th><th>Permissions</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($roles as $role)
                <tr>
                    <td>{{ $role->name }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ $role->permissions->pluck('name')->sort()->join(', ') ?: '-' }}</td>
                    <td>
                        @can('roles.manage')
                            <a href="/admin/roles/{{ $role->id }}/edit">Edit</a>
                        @else
                            <span class="muted">Read only</span>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No roles found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
