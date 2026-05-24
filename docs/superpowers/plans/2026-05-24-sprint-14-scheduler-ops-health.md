# Sprint 14 Scheduler Ops Health Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a self-running Laravel scheduler service plus admin ops health visibility for scheduled maintenance, provisioning queues, provider action queues, overdue services, and bank sync readiness.

**Architecture:** Laravel owns scheduled maintenance orchestration because the commands and encrypted provider/bank secrets live in the backend app. A `scheduled_task_runs` table records each scheduled command execution through a locked-down wrapper command. Docker Compose adds a `scheduler` service running `php artisan schedule:work`, and admin ops health reads database state through a focused snapshot service.

**Tech Stack:** Laravel 13 console commands, Laravel scheduler, Eloquent/PostgreSQL migrations, Blade admin views, Docker Compose, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/plans/2026-05-24-sprint-14-scheduler-ops-health.md`

- [x] Save this S14 implementation plan.
- [x] Scan the plan for placeholders, contradictions, vague steps, and missing spec requirements.
- [x] Commit the plan before production code changes.

Run:

```bash
git add docs/superpowers/plans/2026-05-24-sprint-14-scheduler-ops-health.md
git commit -m "docs: add sprint 14 scheduler ops health plan"
git push origin develop
```

Expected: commit succeeds on `develop`.

### Task 2: RED Tests For Scheduled Task Runs

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ScheduledTaskRunTest.php`

- [x] Add tests for the scheduled task wrapper and runner behavior.

Use this test file:

```php
<?php

namespace Tests\Feature;

use App\Models\ScheduledTaskRun;
use App\Services\Scheduler\ScheduledTaskRegistry;
use App\Services\Scheduler\ScheduledTaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
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
        $this->bindScheduledTasks(['test_success' => 'test:scheduled-task-succeeds']);

        $exitCode = app(ScheduledTaskRunner::class)->run('test_success');

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
        $this->bindScheduledTasks(['test_failure' => 'test:scheduled-task-fails']);

        $exitCode = app(ScheduledTaskRunner::class)->run('test_failure');

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

    public function test_runner_records_exception_output_and_truncates_snippets(): void
    {
        $outputPrefix = 'scheduled task emitted before exception ';
        $errorPrefix = 'scheduled task exception message ';
        $output = $outputPrefix . str_repeat('o', 4100);
        $error = $errorPrefix . str_repeat('e', 4100);

        Artisan::command('test:scheduled-task-throws', function () use ($output, $error): int {
            $this->info($output);

            throw new RuntimeException($error);
        });
        $this->bindScheduledTasks(['test_exception' => 'test:scheduled-task-throws']);

        $exitCode = app(ScheduledTaskRunner::class)->run('test_exception');

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, ScheduledTaskRun::count());
        $run = ScheduledTaskRun::firstOrFail();
        $this->assertSame('test_exception', $run->task);
        $this->assertSame('test:scheduled-task-throws', $run->command);
        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->exit_code);
        $this->assertSame(4000, strlen($run->output));
        $this->assertSame(4000, strlen($run->error));
        $this->assertStringContainsString($outputPrefix, $run->output);
        $this->assertStringContainsString($errorPrefix, $run->error);
    }

    private function bindScheduledTasks(array $tasks): void
    {
        $this->app->instance(ScheduledTaskRegistry::class, new class($tasks) extends ScheduledTaskRegistry {
            /**
             * @param array<string, string> $tasks
             */
            public function __construct(private readonly array $tasks)
            {
            }

            public function all(): array
            {
                return $this->tasks;
            }
        });
    }
}
```

- [x] Run the targeted test on `/opt/billing` and verify RED.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin develop && git reset --hard origin/develop && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=ScheduledTaskRunTest'"
```

Expected: FAIL because `ScheduledTaskRun`, `ScheduledTaskRunner`, and `scheduled-tasks:run` do not exist.

- [x] Commit RED tests.

Run:

```bash
git add apps/backend-laravel/tests/Feature/ScheduledTaskRunTest.php
git commit -m "test: cover scheduled task run logging"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 3: Implement Scheduled Task Run Schema, Runner, And Wrapper

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_130000_create_scheduled_task_runs_table.php`
- Create: `apps/backend-laravel/app/Models/ScheduledTaskRun.php`
- Create: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Create: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRunner.php`
- Create: `apps/backend-laravel/app/Console/Commands/RunScheduledTaskCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`

- [x] Add the migration.

Use:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('task', 80);
            $table->string('command', 255);
            $table->string('status', 30)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['task', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
```

- [x] Add the model.

Use:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['task', 'command', 'status', 'started_at', 'finished_at', 'duration_ms', 'exit_code', 'output', 'error'])]
class ScheduledTaskRun extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'exit_code' => 'integer',
        ];
    }
}
```

- [x] Add the scheduled task registry.

Use:

```php
<?php

namespace App\Services\Scheduler;

class ScheduledTaskRegistry
{
    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return [
            'bank_sync_payments' => 'bank:sync-payments',
            'provider_actions_work' => 'provider-actions:work --limit=50',
            'services_expire' => 'services:expire',
            'provider_actions_recover_stuck' => 'provider-actions:recover-stuck',
        ];
    }

    public function commandFor(string $task): ?string
    {
        return $this->all()[$task] ?? null;
    }
}
```

- [x] Add the runner service.

Use:

```php
<?php

namespace App\Services\Scheduler;

use App\Models\ScheduledTaskRun;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

class ScheduledTaskRunner
{
    private const SNIPPET_LIMIT = 4000;

    public function __construct(private readonly ScheduledTaskRegistry $registry)
    {
    }

    public function run(string $task): int
    {
        $command = $this->registry->commandFor($task);

        if ($command === null) {
            throw new InvalidArgumentException("Unknown scheduled task {$task}.");
        }

        $startedAt = now();
        $started = microtime(true);
        $outputBuffer = new BufferedOutput;

        $run = ScheduledTaskRun::create([
            'task' => $task,
            'command' => $command,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
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
                'output' => $this->snippet($outputBuffer->fetch()),
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
```

- [x] Add the wrapper command.

Use:

```php
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
```

- [x] Register `RunScheduledTaskCommand` in `apps/backend-laravel/bootstrap/app.php`.

Add:

```php
use App\Console\Commands\RunScheduledTaskCommand;
```

And add to `withCommands`:

```php
RunScheduledTaskCommand::class,
```

- [x] Run the scheduled task tests until GREEN on `/opt/billing`.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin feature/sprint-14-scheduler-ops-health && git reset --hard origin/feature/sprint-14-scheduler-ops-health && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'php artisan migrate --force && APP_ENV=testing php artisan test --filter=ScheduledTaskRunTest'"
```

Expected: PASS with 5 tests.

- [x] Commit implementation.

Run:

```bash
git add apps/backend-laravel/database/migrations/2026_05_24_130000_create_scheduled_task_runs_table.php apps/backend-laravel/app/Models/ScheduledTaskRun.php apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRunner.php apps/backend-laravel/app/Console/Commands/RunScheduledTaskCommand.php apps/backend-laravel/bootstrap/app.php
git commit -m "feat: record scheduled task runs"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 4: RED Tests For Laravel Schedule And Compose Scheduler

**Files:**
- Create: `apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php`

- [x] Add tests for schedule list and Compose service configuration.

Note: `Symfony\Component\Yaml\Yaml` is not installed in the backend container, so use built-in text assertions and read `infra/docker-compose.dev.yml` only from local/mounted paths. The backend test runtime must not use network fallbacks; Task 5 mounts `../infra` read-only at `/infra` so the Compose assertions can inspect `/infra/docker-compose.dev.yml`.

Use:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SchedulerConfigurationTest extends TestCase
{
    public function test_laravel_schedule_registers_maintenance_tasks(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('scheduled-tasks:run bank_sync_payments')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_work')
            ->expectsOutputToContain('scheduled-tasks:run services_expire')
            ->expectsOutputToContain('scheduled-tasks:run provider_actions_recover_stuck')
            ->assertExitCode(0);
    }

    public function test_compose_defines_scheduler_service(): void
    {
        $compose = $this->readComposeFile();

        $scheduler = $this->composeServiceBlock($compose, 'scheduler');

        $this->assertStringContainsString('container_name: billing_v3_scheduler', $scheduler);
        $this->assertStringContainsString('php artisan schedule:work', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:.*backend:\s+condition: service_healthy/s', $scheduler);
        $this->assertMatchesRegularExpression('/depends_on:.*postgres:\s+condition: service_healthy/s', $scheduler);
    }

    private function readComposeFile(): string
    {
        $paths = [
            base_path('../../infra/docker-compose.dev.yml'),
            '/infra/docker-compose.dev.yml',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        $this->fail('Expected infra/docker-compose.dev.yml to be readable from the backend test runtime.');
    }

    private function composeServiceBlock(string $compose, string $service): string
    {
        $matched = preg_match(
            sprintf('/^  %s:\R(?P<body>(?: {4}.*\R?)*)/m', preg_quote($service, '/')),
            $compose,
            $matches
        );

        $this->assertSame(1, $matched, "Expected Compose service [{$service}] to be defined.");

        return $matches[0];
    }
}
```

- [x] Run the targeted test on `/opt/billing` and verify RED.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=SchedulerConfigurationTest'"
```

Expected: FAIL because schedule entries and scheduler Compose service do not exist.

- [x] Commit RED tests.

Run:

```bash
git add apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php
git commit -m "test: cover scheduler configuration"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 5: Implement Laravel Schedule And Compose Scheduler Service

**Files:**
- Modify: `apps/backend-laravel/routes/console.php`
- Modify: `infra/docker-compose.dev.yml`

- [x] Register schedule entries in `apps/backend-laravel/routes/console.php`.

Use:

```php
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scheduled-tasks:run bank_sync_payments')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('bank_sync_payments');

Schedule::command('scheduled-tasks:run provider_actions_work')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('provider_actions_work');

Schedule::command('scheduled-tasks:run services_expire')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('services_expire');

Schedule::command('scheduled-tasks:run provider_actions_recover_stuck')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('provider_actions_recover_stuck');
```

- [x] Add a `scheduler` service to `infra/docker-compose.dev.yml`.

Add this service after `backend` and before `worker`:

```yaml
  scheduler:
    build:
      context: ../apps/backend-laravel
      dockerfile: Dockerfile.dev
    container_name: billing_v3_scheduler
    working_dir: /app
    command: ["sh", "-lc", "composer install --no-interaction --prefer-dist && if [ ! -f .env ] || ! grep -q '^DB_CONNECTION=pgsql$' .env; then old_key=$(grep '^APP_KEY=' .env 2>/dev/null | cut -d= -f2-); cp .env.compose.example .env; if [ -n \"$$old_key\" ]; then sed -i \"s|^APP_KEY=.*|APP_KEY=$$old_key|\" .env; else php artisan key:generate --ansi --force; fi; fi && php artisan config:clear && php artisan migrate --force && php artisan schedule:work"]
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      APP_URL: http://localhost:8000
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: billing_v3
      DB_USERNAME: billing
      DB_PASSWORD: billing_secret
      DB_SSLMODE: disable
      SESSION_DRIVER: database
      QUEUE_CONNECTION: database
      CACHE_STORE: database
      REDIS_HOST: redis
      MAIL_MAILER: smtp
      MAIL_HOST: mailpit
      MAIL_PORT: 1025
      BANK_SANDBOX_WEBHOOK_SECRET: local-bank-sandbox-secret
      INTERNAL_PROVISIONING_TOKEN: local-internal-provisioning-token
    volumes:
      - ../apps/backend-laravel:/app
      - ../infra:/infra:ro
    depends_on:
      backend:
        condition: service_healthy
      postgres:
        condition: service_healthy
```

Also add the read-only infra mount to the existing `backend` service so backend feature tests can read `/infra/docker-compose.dev.yml`:

```yaml
    volumes:
      - ../apps/backend-laravel:/app
      - ../infra:/infra:ro
```

- [x] Run scheduler config tests until GREEN.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin feature/sprint-14-scheduler-ops-health && git reset --hard origin/feature/sprint-14-scheduler-ops-health && docker compose -f infra/docker-compose.dev.yml up -d --build backend scheduler && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=SchedulerConfigurationTest'"
```

Expected: PASS with 2 tests.

- [x] Verify Compose config parses.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml config >/tmp/billing-compose-config.out"
```

Expected: exit code 0.

- [x] Commit scheduler runtime slice.

Run:

```bash
git add apps/backend-laravel/routes/console.php infra/docker-compose.dev.yml
git commit -m "feat: add runtime scheduler service"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 6: RED Tests For Ops Health Admin Page

**Files:**
- Create: `apps/backend-laravel/tests/Feature/AdminOpsHealthTest.php`

- [x] Add tests for admin ops health access, counts, and stale task warnings.

Use:

```php
<?php

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningJob;
use App\Models\ScheduledTaskRun;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminOpsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ops_health_counts_and_last_runs(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        $customer = User::factory()->create();
        $service = $this->serviceFor($customer, ['status' => 'active', 'expires_at' => now()->subMinute()]);
        ProvisioningJob::create([
            'order_id' => $service->order_id,
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'type' => 'provision_service',
            'status' => 'failed',
            'attempts' => 3,
            'idempotency_key' => 'ops-health-provisioning-failed',
            'payload' => ['product' => ['code' => $service->product_code]],
            'last_error' => 'Provisioning failed.',
        ]);
        ProviderActionJob::create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'provider_account_id' => null,
            'action' => 'sync',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'idempotency_key' => 'ops-health-provider-pending',
            'payload' => ['context' => []],
        ]);
        BankIntegration::create([
            'provider' => 'private_bank',
            'name' => 'Private Bank',
            'base_url' => 'https://bank.example.test',
            'transactions_path' => '/api/transactions',
            'enabled' => true,
        ]);
        ScheduledTaskRun::create([
            'task' => 'provider_actions_work',
            'command' => 'provider-actions:work --limit=50',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
            'duration_ms' => 50,
            'exit_code' => 0,
            'output' => 'Provider action jobs processed=0 failed=0.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/ops-health')
            ->assertOk()
            ->assertSee('Ops Health')
            ->assertSee('provider_actions_work')
            ->assertSee('success')
            ->assertSee('Provisioning Queue')
            ->assertSee('failed: 1')
            ->assertSee('Provider Action Queue')
            ->assertSee('pending: 1')
            ->assertSeeInOrder(['Overdue Active Services', '1'])
            ->assertSeeInOrder(['Enabled Bank Integrations', '1']);
    }

    public function test_ops_health_warns_when_scheduled_task_is_stale(): void
    {
        $this->travelTo(Carbon::parse('2026-05-24 09:00:00'));
        $admin = $this->adminUser();
        ScheduledTaskRun::create([
            'task' => 'bank_sync_payments',
            'command' => 'bank:sync-payments',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
            'duration_ms' => 25,
            'exit_code' => 0,
            'output' => 'No bank integrations.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/ops-health')
            ->assertOk()
            ->assertSeeInOrder(['bank_sync_payments', 'warning', 'No successful run in the last 3 minutes.']);
    }

    public function test_admin_dashboard_links_to_ops_health(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ops Health')
            ->assertSee('href="/admin/ops-health"', false);
    }

    public function test_customer_cannot_access_ops_health(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)->get('/admin/ops-health')->assertForbidden();
    }

    private function serviceFor(User $user, array $serviceOverrides = []): Service
    {
        $product = Product::factory()->create([
            'code' => 'ops-health-product',
            'name' => 'Ops Health Product',
            'type' => 'proxy',
            'status' => 'active',
            'price_amount' => 99000,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);
        $order = Order::factory()->for($user)->create(['currency' => 'VND']);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'currency' => 'VND',
            'duration_days' => 30,
        ]);

        return Service::factory()->for($user)->for($order)->for($item, 'orderItem')->for($product)->create($serviceOverrides + [
            'product_code' => $product->code,
            'product_name' => $product->name,
            'product_type' => $product->type,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
            'meta' => ['duration_days' => 30],
        ]);
    }

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function customerUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }
}
```

- [x] Run targeted test on `/opt/billing` and verify RED.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=AdminOpsHealthTest'"
```

Expected: FAIL because `/admin/ops-health`, the controller, snapshot service, and view do not exist.

- [x] Commit RED tests.

Run:

```bash
git add apps/backend-laravel/tests/Feature/AdminOpsHealthTest.php
git commit -m "test: cover admin ops health"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 7: Implement Ops Health Snapshot, Route, Controller, And View

**Files:**
- Create: `apps/backend-laravel/app/Services/Ops/OpsHealthSnapshot.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OpsHealthController.php`
- Create: `apps/backend-laravel/resources/views/admin/ops-health/index.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add `OpsHealthSnapshot`.

Use:

```php
<?php

namespace App\Services\Ops;

use App\Models\BankIntegration;
use App\Models\ProviderActionJob;
use App\Models\ProvisioningJob;
use App\Models\ScheduledTaskRun;
use App\Models\Service;

class OpsHealthSnapshot
{
    private const TASKS = [
        'bank_sync_payments' => 3,
        'provider_actions_work' => 3,
        'services_expire' => 15,
        'provider_actions_recover_stuck' => 15,
    ];

    public function data(): array
    {
        return [
            'tasks' => $this->taskHealth(),
            'provisioningQueue' => $this->queueHealth(ProvisioningJob::class),
            'providerActionQueue' => $this->queueHealth(ProviderActionJob::class),
            'overdueActiveServiceCount' => Service::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
            'enabledBankIntegrationCount' => BankIntegration::where('enabled', true)->count(),
        ];
    }

    private function taskHealth(): array
    {
        return collect(self::TASKS)->mapWithKeys(function (int $freshMinutes, string $task): array {
            $lastRun = ScheduledTaskRun::where('task', $task)->latest('started_at')->first();
            $lastSuccess = ScheduledTaskRun::where('task', $task)->where('status', 'success')->latest('finished_at')->first();
            $status = 'ok';
            $message = 'Recent successful run.';

            if (!$lastSuccess || $lastSuccess->finished_at?->lt(now()->subMinutes($freshMinutes))) {
                $status = 'warning';
                $message = "No successful run in the last {$freshMinutes} minutes.";
            }

            if ($lastRun?->status === 'failed') {
                $status = 'failed';
                $message = $lastRun->error ?: 'Last run failed.';
            }

            return [$task => [
                'status' => $status,
                'message' => $message,
                'lastRun' => $lastRun,
                'lastSuccess' => $lastSuccess,
                'freshMinutes' => $freshMinutes,
            ]];
        })->all();
    }

    private function queueHealth(string $modelClass): array
    {
        $pending = $modelClass::where('status', 'pending')->count();
        $processing = $modelClass::where('status', 'processing')->count();
        $failed = $modelClass::where('status', 'failed')->count();
        $status = 'ok';

        if ($failed > 0) {
            $status = 'failed';
        } elseif ($pending + $processing > 0) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'pending' => $pending,
            'processing' => $processing,
            'failed' => $failed,
        ];
    }
}
```

- [ ] Add `OpsHealthController`.

Use:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsHealthSnapshot;
use Illuminate\View\View;

class OpsHealthController extends Controller
{
    public function __invoke(OpsHealthSnapshot $snapshot): View
    {
        return view('admin.ops-health.index', $snapshot->data());
    }
}
```

- [ ] Add the Blade view.

Use:

```blade
@extends('layouts.app', ['title' => 'Ops Health'])
@section('content')
<div class="panel">
    <h1>Ops Health</h1>
    <div class="grid">
        <div class="panel">
            <strong>{{ $provisioningQueue['status'] }}</strong><br>
            Provisioning Queue<br>
            pending: {{ $provisioningQueue['pending'] }}<br>
            processing: {{ $provisioningQueue['processing'] }}<br>
            failed: {{ $provisioningQueue['failed'] }}
        </div>
        <div class="panel">
            <strong>{{ $providerActionQueue['status'] }}</strong><br>
            Provider Action Queue<br>
            pending: {{ $providerActionQueue['pending'] }}<br>
            processing: {{ $providerActionQueue['processing'] }}<br>
            failed: {{ $providerActionQueue['failed'] }}
        </div>
        <div class="panel">
            <strong>{{ $overdueActiveServiceCount }}</strong><br>
            Overdue Active Services
        </div>
        <div class="panel">
            <strong>{{ $enabledBankIntegrationCount }}</strong><br>
            Enabled Bank Integrations
        </div>
    </div>
</div>

<div class="panel">
    <h2>Scheduled Tasks</h2>
    <table>
        <thead><tr><th>Task</th><th>Health</th><th>Last Run</th><th>Duration</th><th>Exit</th><th>Message</th></tr></thead>
        <tbody>
            @foreach ($tasks as $task => $health)
                <tr>
                    <td>{{ $task }}</td>
                    <td>{{ $health['status'] }}</td>
                    <td>{{ $health['lastRun']?->started_at?->toDateTimeString() ?? '-' }}</td>
                    <td>{{ $health['lastRun']?->duration_ms ?? '-' }}</td>
                    <td>{{ $health['lastRun']?->exit_code ?? '-' }}</td>
                    <td>{{ $health['message'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
```

- [ ] Add route import and route in `routes/web.php`.

Add import:

```php
use App\Http\Controllers\Admin\OpsHealthController;
```

Add route inside the admin group:

```php
Route::get('/ops-health', OpsHealthController::class)->middleware('permission:provisioning_jobs.view')->name('ops-health');
```

- [ ] Add dashboard link.

Add inside the admin dashboard link paragraph:

```blade
<a class="button secondary" href="/admin/ops-health">Ops Health</a>
```

- [ ] Run admin ops health tests until GREEN.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin feature/sprint-14-scheduler-ops-health && git reset --hard origin/feature/sprint-14-scheduler-ops-health && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=AdminOpsHealthTest'"
```

Expected: PASS with 3 tests.

- [ ] Commit ops health slice.

Run:

```bash
git add apps/backend-laravel/app/Services/Ops/OpsHealthSnapshot.php apps/backend-laravel/app/Http/Controllers/Admin/OpsHealthController.php apps/backend-laravel/resources/views/admin/ops-health/index.blade.php apps/backend-laravel/routes/web.php apps/backend-laravel/resources/views/admin/dashboard.blade.php
git commit -m "feat: add admin ops health"
git push origin feature/sprint-14-scheduler-ops-health
```

### Task 8: Documentation, Full Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-14-scheduler-ops-health.md`

- [ ] Update README with S14 scheduler and ops health routes/commands.

Add section:

```markdown
## Sprint 14 Scheduler Ops Health

Routes and commands:

- `GET /admin/ops-health`
- `php artisan scheduled-tasks:run bank_sync_payments`
- `php artisan scheduled-tasks:run provider_actions_work`
- `php artisan scheduled-tasks:run services_expire`
- `php artisan scheduled-tasks:run provider_actions_recover_stuck`
- `php artisan schedule:work`

The dev Compose runtime includes a `scheduler` service that runs Laravel `schedule:work`. It records each scheduled command execution in `scheduled_task_runs` and lets admins inspect scheduler freshness, queue counts, failed jobs, overdue services, and enabled bank integrations from `/admin/ops-health`.
```

- [ ] Push branch and check it out on `/opt/billing`.

Run:

```bash
git push origin feature/sprint-14-scheduler-ops-health
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin feature/sprint-14-scheduler-ops-health && git reset --hard origin/feature/sprint-14-scheduler-ops-health"
```

- [ ] Recreate backend, worker, and scheduler; run migrations.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml up -d --build backend worker scheduler && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'php artisan migrate --force && php artisan db:seed --class=DatabaseSeeder --force'"
```

Expected: backend is healthy; worker and scheduler are running.

- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc './vendor/bin/pint --test && APP_ENV=testing php artisan test'"
```

Expected: Pint passes and all Laravel tests pass.

- [ ] Run Go checks on `/opt/billing`.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker run --rm -v /opt/billing/apps/worker-go:/app -w /app golang:1.26.3 sh -lc '/usr/local/go/bin/go fmt ./... && /usr/local/go/bin/go vet ./... && /usr/local/go/bin/go test ./...'"
```

Expected: Go fmt/vet/test pass.

- [ ] Run Docker Compose config/build and secret scan.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml config >/tmp/billing-compose-config.out && docker compose -f infra/docker-compose.dev.yml build backend"
ssh --% root@10.1.1.124 "cd /opt/billing && if git grep -n PAYOS_API_KEY -- . ':(exclude).env.example' ':(exclude)apps/backend-laravel/.env.example' ':(exclude).github/workflows/ci.yml'; then exit 1; fi && if git grep -n MASTER_KEY_BASE64 -- . ':(exclude).env.example' ':(exclude)apps/backend-laravel/.env.example' ':(exclude).github/workflows/ci.yml'; then exit 1; fi && if git grep -n 'PRIVATE KEY' -- . ':(exclude).env.example' ':(exclude)apps/backend-laravel/.env.example' ':(exclude).github/workflows/ci.yml'; then exit 1; fi"
```

Expected: both commands exit 0.

- [ ] Run scheduler command smoke and runtime smoke.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'php artisan scheduled-tasks:run provider_actions_work && php artisan runtime:smoke-provisioning --timeout=30'"
```

Expected: scheduled task run exits 0 and runtime smoke provisions a service.

- [ ] Verify deployed routes and services on branch.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && printf 'up=' && curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/up && printf '\nops_health=' && curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/admin/ops-health && printf '\n' && docker compose -f infra/docker-compose.dev.yml ps --format 'table {{.Name}}\t{{.Service}}\t{{.State}}\t{{.Status}}'"
```

Expected: `/up` is `200`, unauthenticated `/admin/ops-health` is `302`, backend healthy, worker running, scheduler running.

- [ ] Mark verification steps complete in this plan, commit, and push.

Run:

```bash
git add README.md docs/superpowers/plans/2026-05-24-sprint-14-scheduler-ops-health.md
git commit -m "docs: document sprint 14 scheduler ops health"
git push origin feature/sprint-14-scheduler-ops-health
```

- [ ] Open PR to `develop`, wait for CI, merge, and delete feature branch.

Run:

```bash
gh pr create --base develop --head feature/sprint-14-scheduler-ops-health --title "Sprint 14 scheduler ops health" --body "## Summary
- add Laravel scheduled task run logging and allow-listed wrapper command
- add scheduler Compose service running php artisan schedule:work
- add admin ops health page for task freshness and queue health

## Verification
- Laravel Pint
- APP_ENV=testing php artisan test
- containerized go fmt, go vet, go test ./...
- docker compose config and build backend
- secret scan
- scheduled task command smoke
- runtime provisioning smoke"
gh pr checks --watch --interval 10
gh pr merge --merge --delete-branch
```

Expected: CI passes and PR merges.

- [ ] Pull `develop` on `/opt/billing`, recreate backend/worker/scheduler, run smoke, and verify runtime state.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin develop --prune && git checkout develop && git reset --hard origin/develop && docker compose -f infra/docker-compose.dev.yml up -d --build backend worker scheduler && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'php artisan migrate --force && php artisan db:seed --class=DatabaseSeeder --force && php artisan scheduled-tasks:run provider_actions_work && php artisan runtime:smoke-provisioning --timeout=30' && docker compose -f infra/docker-compose.dev.yml ps --format 'table {{.Name}}\t{{.Service}}\t{{.State}}\t{{.Status}}'"
```

Expected: develop is deployed, smoke passes, backend is healthy, worker runs, and scheduler runs.
