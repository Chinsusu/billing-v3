# Sprint 17 Admin Finance Detail Workbench Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin finance drill-down pages for invoices, orders, and payment events.

**Architecture:** Reuse existing finance/order tables. Add read-only admin controller actions and Blade pages. Keep mutation/reconciliation out of this sprint.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-17-admin-finance-detail-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-17-admin-finance-detail.md`

- [x] Save S17 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/AdminFinanceDetailWorkbenchTest.php`

- [x] Add admin invoice detail test.
- [x] Add admin order detail test.
- [x] Add admin payment event detail test.
- [x] Add list link/filter tests.
- [x] Add customer forbidden tests.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Admin Invoice Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/InvoiceController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/invoices/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/invoices/show.blade.php`

- [x] Add status/customer filters to invoice index.
- [x] Add show action loading customer, payment events, and ledger context.
- [x] Add protected route.
- [x] Link invoice rows and customer detail invoice rows to admin invoice detail.
- [x] Render invoice metadata, lines, events, and ledger entries.
- [x] Commit invoice detail slice.

### Task 4: Admin Order Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/OrderController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/orders/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/customers/show.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/orders/show.blade.php`

- [x] Add status/customer filters to order index.
- [x] Add show action loading customer, items, services, provisioning jobs, and ledger context.
- [x] Add protected route.
- [x] Link order rows and customer detail order rows to admin order detail.
- [x] Render order metadata, items, services, jobs, and ledger entries.
- [x] Commit order detail slice.

### Task 5: Admin Payment Event Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/PaymentEventController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/payment-events/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/payment-events/show.blade.php`

- [x] Convert invokable controller to index/show actions.
- [x] Add status/reference filters to payment event index.
- [x] Add protected show route.
- [x] Link payment event rows to detail.
- [x] Render linked intent, wallet, invoice, customer context and payload JSON.
- [x] Run targeted test until GREEN.
- [x] Commit payment event detail slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-17-admin-finance-detail.md`

- [x] Update README with S17 routes.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify `/admin/invoices`, `/admin/orders`, `/admin/payment-events`, and `/up` smoke behavior.
- [x] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
