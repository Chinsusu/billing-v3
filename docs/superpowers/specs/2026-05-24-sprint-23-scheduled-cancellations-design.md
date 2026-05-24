# Sprint 23 Scheduled Cancellation Execution Design

## Goal

Execute customer-requested period-end service cancellations when the service reaches its expiry time.

## Scope

Sprint 23 adds:

- `php artisan service-cancellations:process-scheduled`.
- Scheduler registration through `scheduled-tasks:run service_cancellations_process_scheduled`.
- Due period-end cancellation processing for local services.
- Due period-end cancellation queueing for provider-backed services.
- Completion of the linked `service_cancellations` row after the provider cancel action is processed.
- Ops health visibility for the scheduled cancellation processor.

## Non-Goals

This sprint does not add prorated refunds, automatic refund calculation, bank payouts, arbitrary admin cancellation edits, customer notifications, or provider callback intake. Provider callback intake is Sprint 24.

## Processing Rules

The command processes `service_cancellations` rows where:

- `mode = period_end`
- `status = scheduled`
- related service is active
- related service has `expires_at <= now()`

For local services without a configured provider cancel action:

- The service is marked `cancelled`.
- `service.meta.cancelled_at` is set.
- `service.meta.cancellation.status` becomes `completed`.
- The cancellation row is marked `completed` with `completed_at`.

For provider-backed services with a configured cancel action:

- A provider action job is queued with action `cancel`.
- The idempotency key is `service-cancel:{service_id}:period-end:{cancellation_id}`.
- The service remains `active` until the provider action worker succeeds.
- The cancellation row is marked `queued` and linked to the provider action job.

The command is idempotent: rerunning it does not duplicate provider jobs or re-cancel completed services.

## Provider Action Completion

When the existing provider action worker successfully processes a cancel job, it already marks the service cancelled. Sprint 23 additionally marks any linked `service_cancellations` row as `completed`, records `completed_at`, and appends completion metadata.

## Ops

The task is registered in:

- `ScheduledTaskRegistry`
- Laravel scheduler
- `OpsHealthSnapshot`

This makes the processor visible in scheduled task run history, ops health, and ops alerts.

## Testing

Feature tests cover:

- Due local period-end cancellations complete and cancel the service.
- Future period-end cancellations are skipped.
- Due provider-backed cancellations queue a provider cancel job once.
- Provider action success completes the linked cancellation row.
- The scheduled task registry and schedule list include the new task.
