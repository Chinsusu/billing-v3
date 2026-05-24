<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskRun;
use App\Services\Scheduler\ScheduledTaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledTaskRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_task_wrapper_records_successful_allowed_task(): void
    {
        $this->artisan('scheduled-tasks:run provider_actions_work')
            ->assertExitCode(0);

        $this->assertSame(1, ScheduledTaskRun::count());
        $run = ScheduledTaskRun::firstOrFail();
        $this->assertSame('provider_actions_work', $run->task);
        $this->assertSame('provider-actions:work --limit=50', $run->command);
        $this->assertSame('success', $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertNull($run->error);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertGreaterThanOrEqual($run->started_at, $run->finished_at);
        $this->assertGreaterThanOrEqual(0, $run->duration_ms);
    }

    public function test_runner_records_successful_command_output(): void
    {
        Artisan::command('test:scheduled-task-succeeds', function (): int {
            $this->info('scheduled task succeeded deliberately');

            return 0;
        });

        $exitCode = app(ScheduledTaskRunner::class)->run('test_success', 'test:scheduled-task-succeeds');

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, ScheduledTaskRun::count());
        $run = ScheduledTaskRun::firstOrFail();
        $this->assertSame('test_success', $run->task);
        $this->assertSame('test:scheduled-task-succeeds', $run->command);
        $this->assertSame('success', $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertStringContainsString('scheduled task succeeded deliberately', $run->output);
        $this->assertNull($run->error);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertGreaterThanOrEqual($run->started_at, $run->finished_at);
    }

    public function test_scheduled_task_wrapper_rejects_unknown_task_key(): void
    {
        $this->artisan('scheduled-tasks:run unknown_task')
            ->expectsOutput('Unknown scheduled task unknown_task.')
            ->assertExitCode(1);

        $this->assertSame(0, ScheduledTaskRun::count());
    }

    public function test_runner_records_failed_command(): void
    {
        Artisan::command('test:scheduled-task-fails', function (): int {
            $this->error('scheduled task failed deliberately');

            return 9;
        });

        $exitCode = app(ScheduledTaskRunner::class)->run('test_failure', 'test:scheduled-task-fails');

        $this->assertSame(9, $exitCode);
        $this->assertSame(1, ScheduledTaskRun::count());
        $run = ScheduledTaskRun::firstOrFail();
        $this->assertSame('test_failure', $run->task);
        $this->assertSame('test:scheduled-task-fails', $run->command);
        $this->assertSame('failed', $run->status);
        $this->assertSame(9, $run->exit_code);
        $this->assertStringContainsString('scheduled task failed deliberately', $run->output);
        $this->assertStringContainsString('Command exited with code 9.', $run->error);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertGreaterThanOrEqual($run->started_at, $run->finished_at);
    }
}
