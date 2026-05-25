# Sprint 29 Renewal Policy Controls and Reporting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add product-level renewal policy controls and renewal reporting for admins and customers.

**Architecture:** Product policy fields feed a small renewal policy service. The auto-renew processor consults this policy before attempt creation and again inside the renewal precondition. Reporting reads existing services and attempt rows without changing billing semantics.

**Tech Stack:** Laravel 13, Eloquent UUID models, Artisan commands, Blade, PHPUnit feature tests.

---

### Task 1: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalPolicyTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ServiceAutoRenewalTest.php` only if existing behavior assertions need policy defaults.

- [x] Write tests for product policy persistence, customer toggle guardrails, due-window enforcement, retry delay/max attempts, admin report filtering, and customer reporting surfaces.
- [x] Run targeted tests and verify they fail because schema, policy service, report route, and UI fields do not exist.
- [x] Commit the RED tests.

### Task 2: Schema and Product Controls

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_25_120000_add_auto_renew_policy_to_products_table.php`
- Modify: `apps/backend-laravel/app/Models/Product.php`
- Modify: `apps/backend-laravel/database/factories/ProductFactory.php`
- Modify: `apps/backend-laravel/app/Http/Requests/StoreProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Requests/UpdateProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Modify: `apps/backend-laravel/resources/views/admin/products/_form.blade.php`

- [x] Add product policy columns with safe defaults.
- [x] Add casts, factory defaults, validation, form controls, and audit field coverage.
- [x] Run product-focused tests.
- [x] Commit schema and product control changes.

### Task 3: Policy Enforcement

**Files:**
- Create: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalPolicy.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceAutoRenewalProcessor.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceAutoRenewalController.php`

- [x] Centralize default and per-product policy normalization.
- [x] Refuse customer enable when product policy disallows auto-renewal while preserving disable behavior.
- [x] Enforce policy due window before attempt creation.
- [x] Apply policy retry delay and max attempts on failure and retry skips.
- [x] Re-check policy inside the renewal precondition before wallet/provider side effects.
- [x] Run S28 and S29 auto-renew tests.
- [x] Commit policy enforcement changes.

### Task 4: Admin and Customer Reporting

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceRenewalReportController.php`
- Create: `apps/backend-laravel/resources/views/admin/renewals/index.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/DashboardController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceController.php`
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/services/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/services/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`

- [x] Add admin renewal report route, controller, filters, summary metrics, and links.
- [x] Add customer dashboard and service list renewal metrics.
- [x] Add customer and admin service detail policy hints.
- [x] Run report and UI feature tests.
- [x] Commit reporting changes.

### Task 5: Docs, Verification, PR, and Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-29-renewal-policy-reporting.md`

- [x] Document policy fields, command behavior, and report paths.
- [x] Mark completed plan checklist items.
- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `APP_ENV=testing php artisan test`.
- [ ] Run Go format/vet/test and Compose config/build backend checks used by CI.
- [ ] Push `feature/sprint-29-renewal-policy-reporting`, open a PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run migrations/seeds, and smoke admin/customer renewal pages.
