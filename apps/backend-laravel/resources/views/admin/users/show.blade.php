@extends('layouts.app', ['title' => 'Admin User Detail'])
@section('content')
<div class="panel">
    <h1>User Authorization</h1>
    <p><a href="/admin/users">Back to Users</a></p>
    @can('users.manage')
        <p><a class="button" href="/admin/users/{{ $managedUser->id }}/edit">Edit Authorization</a></p>
    @endcan
    <table>
        <tbody>
            <tr><th>Name</th><td>{{ $managedUser->name }}</td></tr>
            <tr><th>Email</th><td>{{ $managedUser->email }}</td></tr>
            <tr><th>Roles</th><td>{{ implode(', ', $roles) ?: '-' }}</td></tr>
            <tr><th>Direct Permissions</th><td>{{ implode(', ', $directPermissions) ?: '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Effective Permissions</h2>
    <p>{{ implode(', ', $effectivePermissions) ?: '-' }}</p>
</div>
@endsection
