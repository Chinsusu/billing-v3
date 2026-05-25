# Sprint 31 Renewal RBAC and Operator Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add dedicated renewal view/manage permissions and hide/forbid renewal mutation controls from read-only operators.

**Architecture:** Keep S30 renewal business logic unchanged. Implement S31 at the authorization and presentation layers: Spatie permission seed mapping, route middleware, Blade `@can` gates, and focused feature tests proving read-only and manage behavior.

**Tech Stack:** Laravel 13, Spatie Permission, Blade, PHPUnit feature tests, existing admin audit logging.

---

### Task 1: RED Authorization Tests

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalOpsTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalPolicyTest.php`

- [x] Add tests proving `ops_admin` has `renewals.view` and `renewals.manage` after `RolesAndPermissionsSeeder`.
- [x] Add tests proving a user with `renewals.view` only can see `/admin/renewals` but cannot see bulk, retry, or reset controls.
- [x] Add tests proving a user with `renewals.view` only receives 403 on retry, reset, bulk, and admin service auto-renew routes.
- [x] Add tests proving a user with `services.view` but without `renewals.view` cannot access `/admin/renewals`.
- [x] Add tests proving a user with `services.view` but without `renewals.manage` can view service runbook but cannot see `Admin Auto-renew Control`.
- [x] Run targeted tests and verify expected failures because permissions/routes/UI are still S30 behavior.
- [x] Commit RED tests.

### Task 2: Seeder and Route Middleware

**Files:**
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [x] Add `renewals.view` and `renewals.manage` to the permission list.
- [x] Assign both permissions to `super_admin` and `ops_admin`.
- [x] Guard `GET /admin/renewals` with `permission:renewals.view`.
- [x] Guard renewal mutation routes and admin service auto-renew toggle with `permission:renewals.manage`.
- [x] Run targeted authorization tests and verify route-level failures are fixed.
- [x] Commit seeder and route changes.

### Task 3: Blade Permission Gates

**Files:**
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/renewals/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`

- [x] Show dashboard and service index renewal links only under `@can('renewals.view')`.
- [x] In `/admin/renewals`, wrap bulk action panel, select column, checkboxes, and row action forms in `@can('renewals.manage')`.
- [x] Keep the report table readable for users with only `renewals.view`.
- [x] In service runbook, wrap `Admin Auto-renew Control` in `@can('renewals.manage')`.
- [x] Run targeted UI authorization tests.
- [x] Commit UI gating changes.

### Task 4: Docs and Checklist

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-31-renewal-rbac-operator-safety.md`

- [x] Add Sprint 31 README section documenting permissions, routes, role defaults, and deployment seeding.
- [x] Mark completed plan checklist items.
- [x] Run S31 targeted tests.
- [ ] Commit docs/checklist changes.

### Task 5: Verification, PR, Merge, Deploy

- [ ] Run `git diff --check`.
- [ ] Run Laravel formatting and full tests with explicit testing env:
  `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array MAIL_MAILER=array ./vendor/bin/pint --test`
  and
  `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array MAIL_MAILER=array php artisan test`.
- [ ] Run Go `gofmt`, `go vet`, and `go test`.
- [ ] Run Docker Compose config and backend build checks.
- [ ] Run committed-secret scan.
- [ ] Push branch, open PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run `php artisan db:seed --force`, clear caches, and smoke `/admin/renewals`.
