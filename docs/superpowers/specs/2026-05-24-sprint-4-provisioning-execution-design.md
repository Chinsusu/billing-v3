# Sprint 4 Provisioning Execution Design

## Context

Sprint 3 created provisioning outbox rows but did not execute them. Sprint 4 turns the outbox into a working provisioning execution loop while still using a sandbox provider. This keeps the order-to-service path testable before real proxy/VPS provider APIs are wired in.

## Scope

Build provisioning execution:

- Go worker claims one pending provisioning job from the database.
- Worker validates the S3 payload and runs the sandbox provider processor.
- Successful execution marks the service `active`, stores `external_id`, sets `provisioned_at`, and marks the job `processed`.
- Failed execution increments attempts, records `last_error`, and marks the job `failed`.
- Laravel admin can retry failed jobs by resetting them to `pending`.
- Customer service detail page shows status, external ID, and provisioning history.

Sprint 4 does not integrate real provider APIs, run a daemonized deployment process, implement renewal/expiry automation, or consume RabbitMQ messages.

## Backend Changes

The existing `provisioning_jobs` table is sufficient for Sprint 4. Laravel adds admin retry behavior and richer service visibility:

- `POST /admin/provisioning-jobs/{provisioningJob}/retry`
- `GET /services/{service}`
- retry changes `failed` jobs to `pending`, clears `last_error`, and leaves `attempts` for audit
- service detail is owner-scoped

Admin permissions continue using `provisioning_jobs.view`; retry is available to admins with that permission in Sprint 4.

## Worker Changes

The Go worker adds a DB-backed executor:

- `internal/provisioningstore`: claims one job using a transaction and row lock.
- `internal/provisioning`: keeps sandbox provider validation and result generation.
- `cmd/worker`: supports `--once` to process a single job and exit; default remains a lightweight one-shot execution for dev/CI friendliness.

The executor maps the JSON payload to the existing provisioning job DTO. It updates both `services` and `provisioning_jobs` in the same transaction after processing.

## Status Flow

- pending -> processing -> processed
- pending -> processing -> failed
- failed -> pending by admin retry

Processed jobs are not retried. Processing jobs can be recovered manually in later sprints if a real daemon dies mid-job.

## Testing

Laravel feature tests cover service detail authorization and admin retry behavior. Go unit tests use `sqlmock` to verify claim, success update, and failure update behavior without requiring CI Postgres. Existing server verification still runs against the repository in Docker.
