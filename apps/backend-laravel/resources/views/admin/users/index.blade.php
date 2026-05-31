@extends('layouts.admin', ['title' => 'Admin Users'])
@section('content')
<x-page-header title="Users & Roles" eyebrow="Security">
    <x-slot:actions>
        @can('users.manage')
            <a class="button" href="/admin/users/create">Create User</a>
        @endcan
        <a class="button secondary" href="/admin/roles">Roles</a>
    </x-slot:actions>
</x-page-header>

<div class="panel invoice-filter-panel admin-user-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/users">
        <div class="invoice-filter-fields admin-filter-fields--2">
            <label class="invoice-filter-field" for="admin-user-search">
                <span>Search</span>
                <input id="admin-user-search" name="search" value="{{ $filters['search'] }}" placeholder="Search user" list="admin-user-search-options" autocomplete="off">
            </label>
            <label class="invoice-filter-field" for="admin-user-role">
                <span>Role</span>
                <select id="admin-user-role" name="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected($filters['role'] === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary button-soft" href="/admin/users">Clear</a>
        </div>
        <datalist id="admin-user-search-options">
            @foreach ($userSearchOptions as $userOption)
                <option value="{{ $userOption['value'] }}" label="{{ $userOption['label'] }}"></option>
            @endforeach
        </datalist>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Last Login</th><th>Roles</th><th>Direct Permissions</th><th>Action</th></tr></thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->disabled_at ? 'Disabled' : 'Enabled' }}</td>
                    <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '-' }}</td>
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
                <tr><td colspan="7" class="muted">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
