# Sprint 23 Scheduled Cancellation Execution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute due period-end service cancellations and make the processor observable in ops.

**Architecture:** Add a small cancellation processor service and command. Reuse existing provider action queue for provider-backed cancellations, and update the provider action processor to complete linked cancellation rows after successful provider cancel jobs.

**Tech Stack:** Laravel 13, Eloquent, existing scheduled task runner, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-23-scheduled-cancellations-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-23-scheduled-cancellations.md`

- [x] Save S23 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceScheduledCancellationExecutionTest.php`

- [x] Add test for due local period-end cancellation.
- [x] Add test for future scheduled cancellation skipped.
- [x] Add test for due provider-backed cancellation queueing once.
- [x] Add test for provider action success completing linked cancellation row.
- [x] Add scheduler registry and schedule-list assertions.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Scheduled Cancellation Processor

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ScheduledServiceCancellationProcessor.php`
- Create: `apps/backend-laravel/app/Console/Commands/ProcessScheduledServiceCancellationsCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`

- [x] Implement due scheduled cancellation query.
- [x] Complete local due cancellations.
- [x] Queue provider cancel jobs for provider-backed due cancellations.
- [x] Make command output deterministic counts.
- [x] Register command in Laravel bootstrap.
- [x] Run targeted test until command behavior passes.
- [x] Commit processor slice.

### Task 4: Provider Completion and Ops Registration

**Files:**
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProviderActionJobProcessor.php`
- Modify: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Modify: `apps/backend-laravel/app/Services/Ops/OpsHealthSnapshot.php`
- Modify: `apps/backend-laravel/routes/console.php`
- Modify: `apps/backend-laravel/tests/Feature/OpsAlertsTest.php`
- Modify: `apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php`

- [x] Complete linked cancellation rows after successful provider cancel jobs.
- [x] Register `service_cancellations_process_scheduled` in the task registry.
- [x] Schedule the processor every five minutes.
- [x] Add task health coverage for ops health and alerts.
- [x] Update scheduler/ops tests for the new task.
- [x] Run targeted tests until provider completion and scheduler assertions pass.
- [x] Commit provider/ops slice.

### Task 5: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-23-scheduled-cancellations.md`

- [x] Update README with S23 command and behavior.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify `/admin/ops-health`, scheduled task route behavior, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
