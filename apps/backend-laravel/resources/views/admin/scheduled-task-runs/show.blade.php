@extends('layouts.admin', ['title' => 'Scheduled Task Run'])
@section('content')
<div class="panel">
    <p><a href="/admin/scheduled-task-runs?task={{ $run->task }}">Back to {{ $run->task }} Runs</a></p>
    <h1>Scheduled Task Run</h1>
    <table>
        <tbody>
            <tr><th>ID</th><td>{{ $run->id }}</td></tr>
            <tr><th>Task</th><td>{{ $run->task }}</td></tr>
            <tr><th>Command</th><td>{{ $run->command }}</td></tr>
            <tr><th>Status</th><td>{{ $run->status }}</td></tr>
            <tr><th>Started</th><td>{{ $run->started_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Finished</th><td>{{ $run->finished_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Duration</th><td>{{ $run->duration_ms ?? '-' }}</td></tr>
            <tr><th>Exit Code</th><td>{{ $run->exit_code ?? '-' }}</td></tr>
            <tr><th>Created</th><td>{{ $run->created_at?->toDateTimeString() ?? '-' }}</td></tr>
            <tr><th>Updated</th><td>{{ $run->updated_at?->toDateTimeString() ?? '-' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Output</h2>
    <pre>{{ $run->output ?? '-' }}</pre>
</div>

<div class="panel">
    <h2>Error</h2>
    <pre>{{ $run->error ?? '-' }}</pre>
</div>
@endsection
