<?php

namespace App\Console\Commands;

use App\Services\Scheduler\ScheduledTaskRegistry;
use App\Services\Scheduler\ScheduledTaskRunner;
use Illuminate\Console\Command;

class RunScheduledTaskCommand extends Command
{
    protected $signature = 'scheduled-tasks:run {task}';

    protected $description = 'Run one allow-listed scheduled task and record its result.';

    public function handle(ScheduledTaskRegistry $registry, ScheduledTaskRunner $runner): int
    {
        $task = (string) $this->argument('task');
        $command = $registry->commandFor($task);

        if ($command === null) {
            $this->error("Unknown scheduled task {$task}.");

            return self::FAILURE;
        }

        return $runner->run($task);
    }
}
