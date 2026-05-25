# Sprint 30 Renewal Ops Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add audited admin controls for retrying, resetting, and disabling/enabling service auto-renewal from renewal operations surfaces.

**Architecture:** Keep business rules in one `ServiceAutoRenewalOpsService`. Controllers validate request shape and delegate to the service. The ops service applies guardrails, records audit logs, and uses the existing auto-renew processor for immediate retry behavior.

**Tech Stack:** Laravel 13, Eloquent UUID models, Blade, PHPUnit feature tests, existing admin audit logging.

---

### Task 1: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalOpsTest.php`

- [x] Write tests for retry-now success, retry guardrails, reset exhausted attempt, admin toggle, bulk actions, audit rows, and customer authorization.
- [x] Run targeted tests and verify failures because routes/controllers/service do not exist.
- [x] Commit RED tests.

### Task 2: Ops Service

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalOpsService.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalProcessor.php`

- [x] Expose a public `processService(Service $service)` wrapper on the processor that reuses existing `processOne` behavior.
- [x] Add guardrail checks for current expiry target, active status, auto-renew enabled, product policy allowed, due window, and open cancellations.
- [x] Implement `retryNow(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): string`.
- [x] Implement `resetAttempts(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): void`.
- [x] Implement `toggleService(Service $service, bool $enabled, User $actor, string $reason, Request $request): void`.
- [x] Implement bulk retry/disable helpers that return applied/skipped counts.
- [x] Run targeted tests.
- [x] Commit service changes.

### Task 3: Controllers and Routes

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAttemptRetryController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAttemptResetController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalBulkActionController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAdminToggleController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [x] Add admin POST routes under `permission:services.view`.
- [x] Validate required `reason` and boolean/action fields.
- [x] Redirect back with clear status or validation errors.
- [x] Run targeted tests.
- [x] Commit controller/route changes.

### Task 4: Admin UI and Docs

**Files:**
- Modify: `apps/backend-laravel/resources/views/admin/renewals/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-30-renewal-ops-controls.md`

- [x] Add bulk panel, row checkboxes, and row retry/reset controls on `/admin/renewals`.
- [x] Add admin auto-renew toggle form on service runbook.
- [x] Document routes, guardrails, and audit behavior.
- [x] Mark completed plan checklist items.
- [x] Run targeted UI tests.
- [x] Commit UI/docs changes.

### Task 5: Verification, PR, Merge, Deploy

- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `APP_ENV=testing php artisan test`.
- [ ] Run Go format/vet/test and Compose config/build backend checks used by CI.
- [ ] Push branch, open PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run migrations/seeds/cache clear, and smoke `/admin/renewals`.
