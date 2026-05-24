# Sprint 16 Customer Wallet Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin customer search/detail and audited manual wallet adjustments using the existing ledger and wallet service.

**Architecture:** Laravel keeps customer operations in admin controllers and Blade views. Manual wallet movements reuse `WalletService` so balance locking, idempotency, and non-negative debit rules remain centralized.

**Tech Stack:** Laravel 13, Spatie permissions, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-16-customer-wallet-admin-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-16-customer-wallet-admin.md`

- [x] Save the S16 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/AdminCustomerWalletTest.php`

- [ ] Add tests for customer search/detail.
- [ ] Add tests for manual credit/debit ledger audit.
- [ ] Add test for duplicate reference idempotency.
- [ ] Add test for debit overdraw rejection.
- [ ] Add tests for support/customer authorization.
- [ ] Run targeted test on `/opt/billing` and verify RED.
- [ ] Commit RED tests.

### Task 3: Permissions And Admin Customer Backend

**Files:**
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/CustomerController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add `customers.view` and `wallets.adjust` permissions with role mappings.
- [ ] Add admin customer index and show controller actions.
- [ ] Add admin customer routes with permission middleware.
- [ ] Add admin dashboard link.
- [ ] Run customer view/auth tests until backend route failures move to missing views or adjustment.
- [ ] Commit backend route/permission slice.

### Task 4: Manual Wallet Adjustment Service Path

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/CustomerWalletAdjustmentController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [ ] Validate adjustment direction, amount, currency, reason, and optional reference.
- [ ] Use `WalletService::credit` and `WalletService::debit`.
- [ ] Store audit metadata in ledger entry `meta`.
- [ ] Preserve idempotency when a reference is provided.
- [ ] Convert overdraw exceptions into validation errors.
- [ ] Run wallet adjustment tests until route/controller behavior is GREEN except views.
- [ ] Commit wallet adjustment slice.

### Task 5: Admin Views

**Files:**
- Create: `apps/backend-laravel/resources/views/admin/customers/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/customers/show.blade.php`

- [ ] Render searchable customer table with email/name and counts.
- [ ] Render customer detail with wallets, recent ledger entries, invoices, orders, and services.
- [ ] Render adjustment form only when `wallets.adjust` is allowed.
- [ ] Run `AdminCustomerWalletTest` until GREEN.
- [ ] Commit admin views slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-16-customer-wallet-admin.md`

- [ ] Update README with S16 routes and permissions.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/admin/customers` unauthenticated returns `302`.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
