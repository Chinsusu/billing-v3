# Sprint 21 Service Cancellation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add customer cancellation requests and admin refund credits.

**Architecture:** Service cancellations get their own audit table. Provider-backed cancellation reuses the existing provider action queue. Refund credits reuse `WalletService` so ledger locking/idempotency remain centralized.

**Tech Stack:** Laravel 13, PostgreSQL migrations, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-21-service-cancellation-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-21-service-cancellation.md`

- [x] Save S21 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceCancellationFlowTest.php`

- [ ] Add customer local immediate cancellation test.
- [ ] Add customer provider-backed cancellation queue/idempotency test.
- [ ] Add end-of-period cancellation test.
- [ ] Add authorization/status guard tests.
- [ ] Add admin service refund credit test.
- [ ] Run targeted test on `/opt/billing` and verify RED.
- [ ] Commit RED tests.

### Task 3: Cancellation Schema And Model

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_150000_create_service_cancellations_table.php`
- Create: `apps/backend-laravel/app/Models/ServiceCancellation.php`
- Modify: `apps/backend-laravel/app/Models/Service.php`

- [ ] Add cancellation audit table.
- [ ] Add model casts/relationships.
- [ ] Add `Service::cancellations()` relationship.
- [ ] Commit schema/model slice.

### Task 4: Customer Cancellation Flow

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/ServiceCancellationController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/services/show.blade.php`

- [ ] Validate mode and reason.
- [ ] Enforce ownership and active status.
- [ ] Reuse existing open cancellation request idempotently.
- [ ] Queue provider cancel job when configured.
- [ ] Mark local service cancelled when no provider cancel path exists.
- [ ] Render cancellation form and history.
- [ ] Run cancellation tests until GREEN except refund.
- [ ] Commit customer cancellation slice.

### Task 5: Admin Refund Credit

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceRefundCreditController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`

- [ ] Validate amount, currency, reason, and optional reference.
- [ ] Credit customer wallet with source type `service_refund`.
- [ ] Store actor and service metadata in ledger entry.
- [ ] Add refund credit form to admin service runbook.
- [ ] Run `ServiceCancellationFlowTest` until GREEN.
- [ ] Commit refund slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-21-service-cancellation.md`

- [ ] Update README with S21 routes.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/services/{missing}` and `/admin/services` unauthenticated behavior remains safe (`302` for admin list).
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
