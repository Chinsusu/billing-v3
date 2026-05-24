# Sprint 20 Service Runbook Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add service-centric operational detail for admins and safer customer-visible lifecycle/action history.

**Architecture:** Extend existing service controllers and views. Admin detail can show redacted execution log payloads that already passed through `PayloadRedactor`; customer detail only shows statuses/actions/errors and avoids raw request/response payloads.

**Tech Stack:** Laravel 13, Eloquent relations, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-20-service-runbook-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-20-service-runbook.md`

- [x] Save S20 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceRunbookTest.php`

- [x] Add admin service runbook test.
- [x] Add admin service list link test.
- [x] Add customer provider action history test.
- [x] Add customer/admin authorization test.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Admin Service Runbook

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Create: `apps/backend-laravel/resources/views/admin/services/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`

- [x] Add `show` action loading user, order, product, provisioning jobs, provider action jobs, and execution logs.
- [x] Add `GET /admin/services/{service}` route guarded by `services.view`.
- [x] Render admin runbook sections and execution log payloads.
- [x] Link admin services index rows to service detail.
- [x] Commit admin runbook slice.

### Task 4: Customer Service Detail Upgrade

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceController.php`
- Modify: `apps/backend-laravel/resources/views/services/show.blade.php`

- [x] Load provider action jobs and execution logs for customer service detail.
- [x] Render provider action history.
- [x] Render execution status summary without raw request/response payloads.
- [x] Run `ServiceRunbookTest` until GREEN.
- [x] Commit customer detail slice.

### Task 5: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-20-service-runbook.md`

- [x] Update README with S20 route.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify `/admin/services/{seed-or-404-safe}` behavior through unauthenticated `/admin/services` returning `302`.
- [x] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
