# Sprint 30 Renewal Ops Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add audited admin controls for retrying, resetting, and disabling/enabling service auto-renewal from renewal operations surfaces.

**Architecture:** Keep business rules in one `ServiceAutoRenewalOpsService`. Controllers validate request shape and delegate to the service. The ops service applies guardrails, records audit logs, and uses the existing auto-renew processor for immediate retry behavior.

**Tech Stack:** Laravel 13, Eloquent UUID models, Blade, PHPUnit feature tests, existing admin audit logging.

---

### Task 1: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalOpsTest.php`

- [ ] Write tests for retry-now success, retry guardrails, reset exhausted attempt, admin toggle, bulk actions, audit rows, and customer authorization.
- [ ] Run targeted tests and verify failures because routes/controllers/service do not exist.
- [ ] Commit RED tests.

### Task 2: Ops Service

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalOpsService.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalProcessor.php`

- [ ] Expose a public `processService(Service $service)` wrapper on the processor that reuses existing `processOne` behavior.
- [ ] Add guardrail checks for current expiry target, active status, auto-renew enabled, product policy allowed, due window, and open cancellations.
- [ ] Implement `retryNow(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): string`.
- [ ] Implement `resetAttempts(ServiceAutoRenewalAttempt $attempt, User $actor, string $reason, Request $request): void`.
- [ ] Implement `toggleService(Service $service, bool $enabled, User $actor, string $reason, Request $request): void`.
- [ ] Implement bulk retry/disable helpers that return applied/skipped counts.
- [ ] Run targeted tests.
- [ ] Commit service changes.

### Task 3: Controllers and Routes

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAttemptRetryController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAttemptResetController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalBulkActionController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceAutoRenewalAdminToggleController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [ ] Add admin POST routes under `permission:services.view`.
- [ ] Validate required `reason` and boolean/action fields.
- [ ] Redirect back with clear status or validation errors.
- [ ] Run targeted tests.
- [ ] Commit controller/route changes.

### Task 4: Admin UI and Docs

**Files:**
- Modify: `apps/backend-laravel/resources/views/admin/renewals/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-30-renewal-ops-controls.md`

- [ ] Add bulk panel, row checkboxes, and row retry/reset controls on `/admin/renewals`.
- [ ] Add admin auto-renew toggle form on service runbook.
- [ ] Document routes, guardrails, and audit behavior.
- [ ] Mark completed plan checklist items.
- [ ] Run targeted UI tests.
- [ ] Commit UI/docs changes.

### Task 5: Verification, PR, Merge, Deploy

- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `APP_ENV=testing php artisan test`.
- [ ] Run Go format/vet/test and Compose config/build backend checks used by CI.
- [ ] Push branch, open PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run migrations/seeds/cache clear, and smoke `/admin/renewals`.
