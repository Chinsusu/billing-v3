<?php

namespace App\Services\Scheduler;

use App\Models\ScheduledTaskRun;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ScheduledTaskRunner
{
    private const SNIPPET_LIMIT = 4000;

    public function run(string $task, string $command): int
    {
        $startedAt = now();
        $started = microtime(true);

        $run = ScheduledTaskRun::create([
            'task' => $task,
            'command' => $command,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            $outputBuffer = new BufferedOutput;
            $exitCode = Artisan::call($command, [], $outputBuffer);
            $output = $this->snippet($outputBuffer->fetch());
            $error = $exitCode === 0 ? null : "Command exited with code {$exitCode}.";

            $run->forceFill([
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => $error,
            ])->save();

            return $exitCode;
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'exit_code' => 1,
                'output' => $this->snippet(Artisan::output()),
                'error' => $this->snippet($exception->getMessage()),
            ])->save();

            return 1;
        }
    }

    private function snippet(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, self::SNIPPET_LIMIT);
    }
}
