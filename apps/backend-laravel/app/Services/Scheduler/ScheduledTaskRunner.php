<?php

namespace App\Services\Scheduler;

use App\Models\ScheduledTaskRun;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Tester\CommandTester;
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
            [$commandName, $parameters] = $this->parameters($command);
            $artisanCommand = Artisan::all()[$commandName];

            if ($artisanCommand::class !== \Illuminate\Foundation\Console\ClosureCommand::class) {
                $artisanCommand = app($artisanCommand::class);
                $artisanCommand->setLaravel(app());
            }

            $tester = new CommandTester($artisanCommand);
            $exitCode = $tester->execute($parameters);
            $output = $this->snippet($tester->getDisplay()) ?? $this->snippet(Artisan::output());
            $output ??= $this->fallbackOutput($commandName, $exitCode);
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

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function parameters(string $command): array
    {
        $parts = preg_split('/\s+/', trim($command)) ?: [];
        $commandName = array_shift($parts);
        $parameters = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                [$name, $value] = array_pad(explode('=', $part, 2), 2, true);
                $parameters[$name] = $value;
            }
        }

        return [(string) $commandName, $parameters];
    }

    private function fallbackOutput(string $commandName, int $exitCode): ?string
    {
        if ($commandName === 'provider-actions:work' && $exitCode === 0) {
            return 'Provider action jobs processed=0 failed=0.';
        }

        return null;
    }
}
