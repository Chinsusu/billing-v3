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

- [ ] Add status/customer filters to invoice index.
- [ ] Add show action loading customer, payment events, and ledger context.
- [ ] Add protected route.
- [ ] Link invoice rows and customer detail invoice rows to admin invoice detail.
- [ ] Render invoice metadata, lines, events, and ledger entries.
- [ ] Commit invoice detail slice.

### Task 4: Admin Order Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/OrderController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/orders/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/customers/show.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/orders/show.blade.php`

- [ ] Add status/customer filters to order index.
- [ ] Add show action loading customer, items, services, provisioning jobs, and ledger context.
- [ ] Add protected route.
- [ ] Link order rows and customer detail order rows to admin order detail.
- [ ] Render order metadata, items, services, jobs, and ledger entries.
- [ ] Commit order detail slice.

### Task 5: Admin Payment Event Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/PaymentEventController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/payment-events/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/payment-events/show.blade.php`

- [ ] Convert invokable controller to index/show actions.
- [ ] Add status/reference filters to payment event index.
- [ ] Add protected show route.
- [ ] Link payment event rows to detail.
- [ ] Render linked intent, wallet, invoice, customer context and payload JSON.
- [ ] Run targeted test until GREEN.
- [ ] Commit payment event detail slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-17-admin-finance-detail.md`

- [ ] Update README with S17 routes.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/admin/invoices`, `/admin/orders`, `/admin/payment-events`, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
