# Sprint 18 Customer Billing Activity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add customer billing/activity history pages and a useful dashboard snapshot.

**Architecture:** Extend existing customer controllers with index actions. Keep authorization by querying through `Request::user()` relationships or explicit `user_id` constraints. Views use the existing Blade panel/table style.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-18-customer-billing-activity-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-18-customer-billing-activity.md`

- [x] Save S18 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/CustomerBillingActivityTest.php`

- [x] Add tests for customer order index scoping.
- [x] Add tests for customer invoice index scoping.
- [x] Add tests for customer top-up index scoping.
- [x] Add tests for customer dashboard snapshot and links.
- [x] Add tests that guests are redirected from new history pages.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Customer Index Controllers And Routes

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/OrderController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/InvoiceController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/WalletTopUpController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [x] Add `OrderController@index`.
- [x] Add `InvoiceController@index`.
- [x] Add `WalletTopUpController@index`.
- [x] Add `GET /orders`, `GET /invoices`, and `GET /wallet/top-ups` routes.
- [x] Ensure all queries are scoped to the authenticated user.
- [x] Commit controller/route slice.

### Task 4: Customer Views And Dashboard

**Files:**
- Create: `apps/backend-laravel/resources/views/orders/index.blade.php`
- Create: `apps/backend-laravel/resources/views/invoices/index.blade.php`
- Create: `apps/backend-laravel/resources/views/wallet/top-ups/index.blade.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/DashboardController.php`
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`

- [x] Render customer order table with links to detail.
- [x] Render customer invoice table with links to detail/payment.
- [x] Render wallet top-up table with links to QR/detail.
- [x] Add dashboard counts and recent activity.
- [x] Add customer nav links.
- [x] Run `CustomerBillingActivityTest` until GREEN.
- [x] Commit view/dashboard slice.

### Task 5: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-18-customer-billing-activity.md`

- [x] Update README with S18 routes.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify `/orders`, `/invoices`, and `/wallet/top-ups` unauthenticated return `302`.
- [x] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
