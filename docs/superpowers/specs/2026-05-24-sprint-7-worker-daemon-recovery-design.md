# Sprint 7 Worker Daemon Recovery Design

## Context

Sprint 4 made provisioning execution possible, but the Go worker is still a one-shot process and stuck jobs need manual intervention. Sprint 7 turns the provisioning worker into a deployable daemon while keeping the existing `--once` path for safe local tests and manual runs.

This sprint is still provider-neutral. It improves runtime reliability before real proxy/VPS provider adapters are added.

## Scope

Build worker daemon and recovery behavior:

- `cmd/worker --daemon` runs continuously and polls for provisioning jobs.
- `cmd/worker --once` still processes one job and exits.
- Worker config reads `WORKER_POLL_INTERVAL`, `PROVISIONING_STUCK_AFTER`, `PROVISIONING_MAX_ATTEMPTS`, and `PROVISIONING_RETRY_BACKOFF`.
- Failed processing attempts are retried with `available_at` backoff until max attempts.
- Jobs at or above max attempts become `failed` with `last_error`.
- Jobs stuck in `processing` longer than `PROVISIONING_STUCK_AFTER` are recovered.
- Stuck jobs below max attempts return to `pending`; stuck jobs at max attempts become `failed`.
- Docker Compose gains a worker service that runs `go run ./cmd/worker --daemon`.
- Laravel admin provisioning job list surfaces `available_at`, `processed_at`, and `last_error` clearly.

Sprint 7 does not call real provider APIs, add RabbitMQ consumption, add supervisor/systemd production deployment, or change wallet/payment flows.

## Worker Runtime

The worker keeps a small, testable loop:

1. Recover stuck `processing` jobs.
2. Claim and process one pending job.
3. If no job was processed, sleep for `WORKER_POLL_INTERVAL`.
4. Repeat until the context is cancelled or the process receives a termination signal.

The loop treats per-job processing errors as handled attempts when the store records retry/failure state. Database or recovery errors are returned so the process exits loudly in dev/test.

## Retry And Recovery

Retry policy:

- Default max attempts: `3`.
- Default retry backoff: `60s`.
- Failed attempt with `attempts < max_attempts`: set job to `pending`, set `available_at = now() + retry_backoff`, keep `processed_at = null`, store `last_error`.
- Failed attempt with `attempts >= max_attempts`: set job to `failed`, set `processed_at = now()`, store `last_error`.

Recovery policy:

- A stuck job is `status = processing` and `updated_at <= now() - stuck_after`.
- Stuck job with `attempts < max_attempts`: set `status = pending`, `available_at = now()`, and `last_error = recovered stuck processing job`.
- Stuck job with `attempts >= max_attempts`: set `status = failed`, `processed_at = now()`, and `last_error = recovered stuck processing job after max attempts`.

## Docker Dev Runtime

`infra/docker-compose.dev.yml` adds a `worker` service:

- image: `golang:1.26.3`
- bind mount: `../apps/worker-go:/app`
- command: `go run ./cmd/worker --daemon`
- environment points `DATABASE_URL` at the Compose Postgres service.
- depends on Postgres health.

The service is intended for dev/test runtime only. CI still uses direct Go commands.

## Admin Visibility

The existing `/admin/provisioning-jobs` page stays read-only except for failed-job retry from Sprint 4. Sprint 7 adds columns for:

- available time
- processed time
- last error

This gives operators enough state to understand backoff, retry, and stuck recovery outcomes.

## Testing

Go unit tests cover:

- config parsing for new worker runtime variables.
- retry backoff when a processing attempt fails below max attempts.
- final failure when attempts reach max attempts.
- stuck processing recovery to pending.
- stuck processing recovery to failed at max attempts.
- daemon loop sleeps when no job is available and processes repeated jobs in daemon mode.

Laravel feature tests cover admin provisioning job visibility for available time, processed time, and last error.

Docker compose config validation covers the new worker service.
