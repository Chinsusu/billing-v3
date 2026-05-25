# Sprint 28 Auto-Renewal Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-service wallet-funded auto-renewal with scheduler execution, retry history, failure notifications, and customer/admin visibility.

**Architecture:** Auto-renewal is an orchestration layer around the existing manual `ServiceRenewalService`. The scheduler creates one attempt row for each service expiry target, retries failed rows after a short backoff, and leaves wallet/provider idempotency to the renewal service. UI changes are read/write on the service detail page for customers and read-only on the admin runbook.

**Tech Stack:** Laravel 13, Eloquent UUID models, Artisan scheduled commands, Blade, PHPUnit feature tests.

---

### Task 1: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalTest.php`
- Modify: `apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php`
- Modify: `apps/backend-laravel/tests/Feature/AdminOpsHealthTest.php`

- [ ] Write tests for customer enable/disable, ownership protection, successful scheduled renewal, insufficient-balance retry behavior, cancellation skip behavior, scheduler registration, and ops health visibility.
- [ ] Run `APP_ENV=testing php artisan test tests/Feature/ServiceAutoRenewalTest.php tests/Feature/SchedulerConfigurationTest.php tests/Feature/AdminOpsHealthTest.php`.
- [ ] Verify the tests fail because the auto-renew column, attempt table, route, command, and scheduler task do not exist.
- [ ] Commit the RED tests.

### Task 2: Schema and Model

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_25_110000_add_auto_renew_to_services_table.php`
- Create: `apps/backend-laravel/database/migrations/2026_05_25_110100_create_service_auto_renewal_attempts_table.php`
- Create: `apps/backend-laravel/app/Models/ServiceAutoRenewalAttempt.php`
- Modify: `apps/backend-laravel/app/Models/Service.php`
- Modify: `apps/backend-laravel/database/factories/ServiceFactory.php`

- [ ] Add `services.auto_renew_enabled` with a false default.
- [ ] Add `service_auto_renewal_attempts` with service/user relations, target expiry, status, attempt count, retry time, renewed expiry, price snapshot, last error, and unique idempotency key.
- [ ] Add model casts and `Service::autoRenewalAttempts()`.
- [ ] Add factory default `auto_renew_enabled => false`.
- [ ] Run the auto-renew tests and verify failures move from missing schema/model to missing route/processor behavior.
- [ ] Commit schema and model changes.

### Task 3: Customer Toggle and Service Visibility

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/ServiceAutoRenewalController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceController.php`
- Modify: `apps/backend-laravel/resources/views/services/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`

- [ ] Add `POST /services/{service}/auto-renew` for the owner to set `enabled`.
- [ ] Reject other users with 404 and inactive services with a validation error.
- [ ] Load latest auto-renewal attempts on customer and admin service detail pages.
- [ ] Render customer toggle, state, and latest attempt summary.
- [ ] Render admin read-only state and attempt history.
- [ ] Run `APP_ENV=testing php artisan test tests/Feature/ServiceAutoRenewalTest.php` and verify UI/toggle tests pass or expose processor-only failures.
- [ ] Commit customer toggle and visibility changes.

### Task 4: Processor, Command, Scheduler, and Notifications

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalProcessor.php`
- Create: `apps/backend-laravel/app/Console/Commands/AutoRenewServicesCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Modify: `apps/backend-laravel/routes/console.php`
- Modify: `apps/backend-laravel/app/Services/Ops/OpsHealthSnapshot.php`
- Modify: `apps/backend-laravel/app/Services/Notifications/NotificationTemplateCatalog.php`

- [ ] Implement candidate selection for active opted-in services expiring within one day and without open cancellation.
- [ ] Reuse or create one attempt row per service/expiry idempotency key.
- [ ] Call `ServiceRenewalService::renew()` and mark success with renewed expiry, amount, and currency.
- [ ] On failure, store the exception message, set `next_attempt_at = now() + 1 hour`, and enqueue `service_auto_renew_failed` once per attempt count.
- [ ] Add `services:auto-renew --limit=50`.
- [ ] Register `services_auto_renew` in the task registry, Laravel schedule, and ops health freshness checks.
- [ ] Add the notification template catalog entry.
- [ ] Run targeted auto-renew, scheduler, ops health, and notification preference tests.
- [ ] Commit processor, scheduler, and notification changes.

### Task 5: Docs, Verification, PR, and Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-28-auto-renewal.md`

- [ ] Document S28 auto-renew behavior, command, schedule, UI path, and retry semantics.
- [ ] Mark completed plan checklist items.
- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `APP_ENV=testing php artisan test`.
- [ ] Run Go format/vet/test and Compose config/build backend checks used by CI.
- [ ] Push `feature/sprint-28-auto-renewal`, open a PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run migrations/seeds, and smoke the service detail page.
