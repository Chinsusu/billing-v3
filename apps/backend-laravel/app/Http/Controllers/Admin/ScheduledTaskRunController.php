<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduledTaskRun;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduledTaskRunController extends Controller
{
    public function index(Request $request, ScheduledTaskRegistry $registry): View
    {
        $filters = [
            'task' => $request->query('task'),
            'status' => $request->query('status'),
        ];

        $runs = ScheduledTaskRun::query()
            ->when($filters['task'], fn ($query, string $task) => $query->where('task', $task))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->latest('started_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.scheduled-task-runs.index', [
            'runs' => $runs,
            'filters' => $filters,
            'tasks' => array_keys($registry->all()),
            'statuses' => ['running', 'success', 'failed'],
        ]);
    }

    public function show(ScheduledTaskRun $scheduledTaskRun): View
    {
        return view('admin.scheduled-task-runs.show', [
            'run' => $scheduledTaskRun,
        ]);
    }
}
