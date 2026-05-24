# Sprint 14 Scheduler Ops Health Design

Sprint 14 turns the runtime automation added in earlier sprints into a self-running operational loop. Sprint 13 added durable provider action jobs, but several important commands still require manual execution. This sprint adds a Docker Compose scheduler service, records scheduled task runs, and gives admins a health page for queues and automation status.

## Goals

- Run recurring billing/provisioning maintenance from the app runtime without host crontab setup.
- Record scheduled task starts, successes, failures, duration, output, and error snippets.
- Surface queue and scheduler health in the admin UI.
- Keep failed scheduled commands isolated so one failure does not stop the scheduler loop.
- Keep the implementation local/dev friendly while matching a production pattern that can later be moved to a process manager or orchestrator.

## Non-Goals

- Replacing the existing Go provisioning worker.
- Moving provider action processing to Go.
- Building notification channels for health alerts.
- Adding external monitoring integrations.
- Building a full metrics/time-series system.

## Scheduler Runtime

Add a `scheduler` service to `infra/docker-compose.dev.yml`. It uses the same backend image, mounted Laravel app, environment variables, and Postgres dependency as `backend`, but runs:

```bash
php artisan schedule:work
```

The scheduler service depends on the backend being healthy and Postgres being healthy. It does not expose ports. It should restart with the Compose stack and run alongside backend and worker.

Laravel's console schedule registers these tasks:

- `bank:sync-payments` every minute.
- `provider-actions:work --limit=50` every minute.
- `services:expire` every five minutes.
- `provider-actions:recover-stuck` every five minutes.

The scheduler should use `withoutOverlapping` where supported so a slow task does not create concurrent copies of the same task. Task names are stable strings used for logging and health display:

- `bank_sync_payments`
- `provider_actions_work`
- `services_expire`
- `provider_actions_recover_stuck`

## Scheduled Task Run Logging

Add `scheduled_task_runs`:

- `id` UUID primary key
- `task` string
- `command` string
- `status`: `running`, `success`, or `failed`
- `started_at` timestamp
- `finished_at` nullable timestamp
- `duration_ms` nullable unsigned integer
- `exit_code` nullable integer
- `output` nullable text
- `error` nullable text
- timestamps

Add model `ScheduledTaskRun` with casts and fillable fields.

Add a scheduler runner service responsible for executing a command and writing one run row. The runner:

1. Creates a `running` row before command execution.
2. Executes the command through Laravel's Artisan API.
3. Captures command output up to a reasonable snippet size.
4. Marks the run `success` when exit code is `0`.
5. Marks the run `failed` when exit code is non-zero or an exception is thrown.
6. Always sets `finished_at` and `duration_ms`.

The scheduler calls a single wrapper command such as:

```bash
php artisan scheduled-tasks:run bank_sync_payments
```

That wrapper maps stable task keys to allowed commands. It rejects unknown task keys so arbitrary commands cannot be executed through the scheduler endpoint.

## Ops Health Admin Page

Add:

- `GET /admin/ops-health`

Use the existing `admin.access` group and `provisioning_jobs.view` permission because the page primarily exposes operational queue state.

The page shows:

- Last run status for each scheduled task.
- Last run time, duration, and last error snippet.
- Pending, processing, and failed `provisioning_jobs` counts.
- Pending, processing, and failed `provider_action_jobs` counts.
- Count of overdue active services.
- Count of enabled bank integrations.
- Simple health labels: `ok`, `warning`, or `failed`.

Task freshness rules:

- `bank_sync_payments`: warning if no success in the last 3 minutes.
- `provider_actions_work`: warning if no success in the last 3 minutes.
- `services_expire`: warning if no success in the last 15 minutes.
- `provider_actions_recover_stuck`: warning if no success in the last 15 minutes.

Failed queue rules:

- Any failed provisioning job marks provisioning queue health `failed`.
- Any failed provider action job marks provider action health `failed`.
- Pending or processing jobs without failures are `warning` when count is greater than zero and `ok` when zero.

## Data Flow

1. Compose starts backend, worker, and scheduler.
2. Scheduler runs Laravel `schedule:work`.
3. Laravel schedule triggers `scheduled-tasks:run <task>`.
4. The wrapper resolves the task key to one allowed command.
5. The runner records a `scheduled_task_runs` row and executes the command.
6. Admin opens `/admin/ops-health` to inspect last runs and queue state.

## Error Handling

- Unknown task key exits non-zero and does not execute any command.
- A failed task records `failed` with exit code and error/output snippet.
- Exceptions are caught and recorded as failed runs.
- Scheduler loop continues after failures because each task invocation is isolated.
- Output and error snippets are truncated so a noisy command cannot bloat the database.

## Testing

Laravel tests cover:

- Running `scheduled-tasks:run bank_sync_payments` creates a successful task run when the underlying command exits `0`.
- A failing mapped command records a failed run with non-zero exit code or exception details.
- Unknown task keys are rejected.
- `/admin/ops-health` shows scheduled task run state, queue counts, failed jobs, and overdue service counts.
- Customer users cannot access `/admin/ops-health`.
- Docker Compose config includes a `scheduler` service running `php artisan schedule:work`.

Full verification includes:

- Laravel Pint.
- Full Laravel test suite.
- Go `fmt`, `vet`, and `test`.
- Docker Compose config/build.
- Secret scan.
- Deploy to `/opt/billing`, recreate backend/worker/scheduler, run migrations, and verify health routes.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Recreate backend, worker, and scheduler services.
3. Run migrations and seed idempotent dev data.
4. Verify `docker compose ps` shows backend healthy, worker running, and scheduler running.
5. Run `php artisan scheduled-tasks:run provider_actions_work` manually once.
6. Verify `/admin/ops-health` redirects unauthenticated users to login and renders for the seeded admin.
