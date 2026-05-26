@extends('layouts.admin', ['title' => 'Scheduled Task Runs'])
@section('content')
<div class="panel">
    <h1>Scheduled Task Runs</h1>
    <p><a class="button secondary" href="/admin/ops-health">Ops Health</a></p>
    <form method="GET" action="/admin/scheduled-task-runs">
        <div class="grid">
            <div>
                <label for="task">Task</label>
                <select id="task" name="task">
                    <option value="">All tasks</option>
                    @foreach ($tasks as $task)
                        <option value="{{ $task }}" @selected($filters['task'] === $task)>{{ $task }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p>
            <button type="submit">Filter</button>
            <a class="button secondary" href="/admin/scheduled-task-runs">Reset</a>
        </p>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>Task</th><th>Command</th><th>Status</th><th>Started</th><th>Finished</th><th>Duration</th><th>Exit</th><th>Output/Error</th><th></th></tr></thead>
        <tbody>
            @forelse ($runs as $run)
                <tr>
                    <td>{{ $run->task }}</td>
                    <td>{{ $run->command }}</td>
                    <td>{{ $run->status }}</td>
                    <td>{{ $run->started_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $run->finished_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $run->duration_ms ?? '-' }}</td>
                    <td>{{ $run->exit_code ?? '-' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($run->error ?? $run->output ?? '-', 120) }}</td>
                    <td><a class="button secondary" href="/admin/scheduled-task-runs/{{ $run->id }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No scheduled task runs found.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $runs->links() }}
</div>
@endsection
