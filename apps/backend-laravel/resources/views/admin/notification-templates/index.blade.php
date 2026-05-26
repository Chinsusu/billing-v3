@extends('layouts.admin', ['title' => 'Notification Templates'])
@section('content')
<div class="panel">
    <h1>Notification Templates</h1>
    <table>
        <thead><tr><th>Type</th><th>Channel</th><th>Name</th><th>Enabled</th><th>Variables</th><th></th></tr></thead>
        <tbody>
            @forelse ($notificationTemplates as $template)
                <tr>
                    <td>{{ $template->type }}</td>
                    <td>{{ $template->channel }}</td>
                    <td>{{ $template->name }}</td>
                    <td>{{ $template->enabled ? 'Yes' : 'No' }}</td>
                    <td>{{ implode(', ', $template->variables ?? []) }}</td>
                    <td><a href="/admin/notification-templates/{{ $template->id }}/edit">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No notification templates yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
