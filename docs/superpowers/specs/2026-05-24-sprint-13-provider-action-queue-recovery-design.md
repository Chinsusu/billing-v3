# Sprint 13 Provider Action Queue Recovery Design

Sprint 13 makes provider lifecycle actions operationally reliable. Sprint 12 executes renew, suspend, and sync directly in the HTTP request or command. Sprint 13 introduces a Laravel-owned provider action queue for suspend, cancel, and sync so slow or failing providers can be retried, recovered, and inspected without hardcoding provider-specific behavior.

## Goals

- Store provider lifecycle action work in a durable queue.
- Process queued `suspend`, `cancel`, and `sync` actions through Laravel because provider secrets remain encrypted in Laravel.
- Add retry backoff, max attempts, and stuck-job recovery for provider action jobs.
- Keep `renew` synchronous so wallet debit still happens only after provider renew succeeds.
- Let admins enqueue sync/cancel actions from the services table.
- Let admins inspect provider action jobs and retry failed jobs.
- Preserve existing redacted `provisioning_execution_logs` for each provider HTTP attempt.

## Non-Goals

- Moving provider action processing to Go.
- Async renewal or `renewal_pending` wallet reservation states.
- Customer-facing cancellation flow or refund rules.
- Provider-specific drivers for Vultr, DigitalOcean, Linode, or bank APIs.
- Cron scheduling inside the app; cron/systemd can call the Laravel commands.

## Data Model

Add `provider_action_jobs`:

- `id` UUID primary key
- `service_id` FK to `services`
- `user_id` FK to `users`
- `provider_account_id` nullable FK to `provisioning_provider_accounts`
- `action`: `suspend`, `cancel`, or `sync`
- `status`: `pending`, `processing`, `processed`, or `failed`
- `attempts` unsigned integer
- `max_attempts` unsigned integer, default `3`
- `idempotency_key` unique string
- `payload` JSON containing context and requested local updates
- `available_at` nullable timestamp
- `processed_at` nullable timestamp
- `last_error` nullable text
- timestamps

This queue is separate from `provisioning_jobs` because provider actions operate on already-created services and may be triggered by expiry automation or admin operations, not product checkout.

## Dispatch

Laravel gets `ProviderActionJobDispatcher`:

- `enqueue(Service $service, string $action, string $idempotencyKey, array $context = [])`
- Reuses an existing job when `idempotency_key` already exists.
- Captures `provider_account_id` from the service metadata provider snapshot or product fallback.
- Creates a pending job only for supported async actions: `suspend`, `cancel`, `sync`.

`php artisan services:expire` changes behavior:

- Overdue active service with no configured suspend endpoint is marked `expired` locally, same as before.
- Overdue active service with configured suspend endpoint gets a `suspend` job instead of calling provider immediately.
- Local service remains active until the queued provider suspend succeeds.

Admin services:

- `POST /admin/services/{service}/sync-provider` enqueues a `sync` job.
- `POST /admin/services/{service}/cancel-provider` enqueues a `cancel` job.

## Processing

Add `php artisan provider-actions:work`:

- Claims pending jobs whose `available_at` is null or due.
- Marks one job `processing`, increments attempts, and executes it through `ProviderServiceActionService`.
- On success:
  - `sync`: updates local status and expiry when provider response has them.
  - `suspend`: marks service `expired` and writes `meta.expired_at`.
  - `cancel`: marks service `cancelled` and writes `meta.cancelled_at`.
  - marks job `processed`.
- On failure:
  - if attempts are below max attempts, requeues job with retry backoff.
  - if attempts reach max attempts, marks job `failed`.

Add `php artisan provider-actions:recover-stuck`:

- Requeues stale `processing` jobs below max attempts.
- Fails stale `processing` jobs at or above max attempts.

Default retry values:

- max attempts: `3`
- retry backoff: `60` seconds
- stuck threshold: `5` minutes

These defaults can be command options later; Sprint 13 keeps them as service constants.

## Admin Operations

Add admin routes:

- `GET /admin/provider-action-jobs`
- `POST /admin/provider-action-jobs/{providerActionJob}/retry`

The index shows job action, service, provider account, status, attempts, availability, processed time, and last error. The retry endpoint only requeues failed jobs.

The existing admin services table adds a provider cancel button beside sync. Cancel is admin-only; customer cancellation and refunds remain a later sprint.

## Error Handling

- Queue processing uses existing provider action normalized errors through `ProviderServiceActionService`.
- Failed attempts keep `last_error`.
- Retry clears `processed_at`, sets status back to `pending`, and schedules immediate availability.
- Duplicate enqueue attempts return the existing job and do not create another row.
- Missing provider path is treated as a dispatch validation error for admin-triggered actions; `services:expire` only enqueues suspend when the path is configured.

## Testing

Laravel tests cover:

- Dispatch creates idempotent provider action jobs.
- `services:expire` enqueues suspend jobs and does not mark provider-backed services expired before processing.
- Worker command processes suspend success and marks service expired.
- Worker command retries suspend failure with backoff and fails at max attempts.
- Worker command processes sync and updates local status/expiry.
- Worker command processes cancel and marks service cancelled.
- Stuck recovery requeues or fails processing jobs according to attempts.
- Admin can view provider action jobs, retry a failed job, enqueue sync, and enqueue cancel.

Go worker tests do not change.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Recreate backend and run migrations.
3. Recreate worker for consistency even though Go is unchanged.
4. Run `php artisan provider-actions:work --once` against an empty queue to verify the command boots.
5. Run `php artisan runtime:smoke-provisioning --timeout=30`.
6. Verify `/products`, `/admin/services`, `/admin/provider-action-jobs`, backend health, and worker state.
