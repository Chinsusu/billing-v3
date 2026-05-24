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

- [ ] Add tests for customer order index scoping.
- [ ] Add tests for customer invoice index scoping.
- [ ] Add tests for customer top-up index scoping.
- [ ] Add tests for customer dashboard snapshot and links.
- [ ] Add tests that guests are redirected from new history pages.
- [ ] Run targeted test on `/opt/billing` and verify RED.
- [ ] Commit RED tests.

### Task 3: Customer Index Controllers And Routes

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/OrderController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/InvoiceController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/WalletTopUpController.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [ ] Add `OrderController@index`.
- [ ] Add `InvoiceController@index`.
- [ ] Add `WalletTopUpController@index`.
- [ ] Add `GET /orders`, `GET /invoices`, and `GET /wallet/top-ups` routes.
- [ ] Ensure all queries are scoped to the authenticated user.
- [ ] Commit controller/route slice.

### Task 4: Customer Views And Dashboard

**Files:**
- Create: `apps/backend-laravel/resources/views/orders/index.blade.php`
- Create: `apps/backend-laravel/resources/views/invoices/index.blade.php`
- Create: `apps/backend-laravel/resources/views/wallet/top-ups/index.blade.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/DashboardController.php`
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`

- [ ] Render customer order table with links to detail.
- [ ] Render customer invoice table with links to detail/payment.
- [ ] Render wallet top-up table with links to QR/detail.
- [ ] Add dashboard counts and recent activity.
- [ ] Add customer nav links.
- [ ] Run `CustomerBillingActivityTest` until GREEN.
- [ ] Commit view/dashboard slice.

### Task 5: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-18-customer-billing-activity.md`

- [ ] Update README with S18 routes.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/orders`, `/invoices`, and `/wallet/top-ups` unauthenticated return `302`.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
