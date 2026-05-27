@extends('layouts.admin', ['title' => 'Scheduled Task Runs'])
@section('content')
<x-page-header title="Scheduled Task Runs" eyebrow="Queue & Ops" />

<div class="panel invoice-filter-panel scheduled-task-filter-panel">
    <form class="invoice-filter-form" method="GET" action="/admin/scheduled-task-runs">
        <div class="invoice-filter-fields admin-filter-fields--2">
            <label class="invoice-filter-field" for="task">
                <span>Task</span>
                <select id="task" name="task">
                    <option value="">All tasks</option>
                    @foreach ($tasks as $task)
                        <option value="{{ $task }}" @selected($filters['task'] === $task)>{{ $task }}</option>
                    @endforeach
                </select>
            </label>
            <label class="invoice-filter-field" for="status">
                <span>Status</span>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="invoice-filter-actions">
            <button type="submit">Filter</button>
            <a class="button secondary button-soft" href="/admin/scheduled-task-runs">Reset</a>
            <a class="button secondary button-soft" href="/admin/ops-health">Ops Health</a>
        </div>
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
