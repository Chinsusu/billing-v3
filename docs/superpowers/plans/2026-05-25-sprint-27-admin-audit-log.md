# Sprint 27 Admin Audit Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add append-only admin audit logs for sensitive config, money, and operations actions.

**Architecture:** Laravel stores audit rows in `admin_audit_logs`. A small `AuditLogger` service sanitizes payloads and records route/request context. Existing admin controllers call the logger immediately after successful mutations so each row has an authenticated actor and domain-specific metadata.

**Tech Stack:** Laravel 13, Eloquent UUID models, Blade admin views, Spatie permissions, PHPUnit feature tests.

---

### Task 1: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/AdminAuditLogTest.php`

- [ ] Write feature tests for bank/provider secret redaction, product/template/manual operational actions, and UI access control.
- [ ] Run `APP_ENV=testing php artisan test tests/Feature/AdminAuditLogTest.php` on the dev container and verify it fails because `AdminAuditLog` and `/admin/audit-logs` do not exist.
- [ ] Commit the RED test.

### Task 2: Schema, Model, and Logger

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_25_100000_create_admin_audit_logs_table.php`
- Create: `apps/backend-laravel/app/Models/AdminAuditLog.php`
- Create: `apps/backend-laravel/app/Services/Audit/AuditLogger.php`
- Modify: `apps/backend-laravel/app/Models/User.php`

- [ ] Add the `admin_audit_logs` migration with actor, subject, JSON payload, request context, and indexes.
- [ ] Add the Eloquent model with JSON casts and `actor()` relation.
- [ ] Add `AuditLogger::record()` and `AuditLogger::diff()` with recursive secret redaction.
- [ ] Add `User::adminAuditLogs()` relation.
- [ ] Run the audit test and verify failures move from missing schema/model to missing controller hooks.
- [ ] Commit schema and service.

### Task 3: Admin Hooks

**Files:**
- Modify: `apps/backend-laravel/app/Services/Finance/BankIntegrationService.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningProviderAccountController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/NotificationTemplateController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertRuleController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/CustomerWalletAdjustmentController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceRefundCreditController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceProviderSyncController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceProviderCancelController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProviderActionJobRetryController.php`

- [ ] Inject/call `AuditLogger` after each successful mutation.
- [ ] Use `diff()` for create/update/archive rows and explicit metadata for wallet/refund/provider queue/retry actions.
- [ ] Keep raw secrets out of `before`, `after`, and `metadata`.
- [ ] Run `APP_ENV=testing php artisan test tests/Feature/AdminAuditLogTest.php` and verify data assertions pass or expose UI-only failures.
- [ ] Commit admin audit hooks.

### Task 4: Admin UI and Permissions

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/AdminAuditLogController.php`
- Create: `apps/backend-laravel/resources/views/admin/audit-logs/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/audit-logs/show.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add `audit_logs.view` permission for `super_admin`.
- [ ] Add index/detail routes guarded by `audit_logs.view`.
- [ ] Add filters for actor, action, auditable type, and auditable id.
- [ ] Render sanitized JSON in detail view.
- [ ] Link Audit Logs from the admin dashboard.
- [ ] Run `APP_ENV=testing php artisan test tests/Feature/AdminAuditLogTest.php` and verify it passes.
- [ ] Commit UI and permission changes.

### Task 5: Docs and Verification

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-27-admin-audit-log.md`

- [ ] Update README with S27 routes and audit behavior.
- [ ] Mark plan checklist items complete.
- [ ] Run `./vendor/bin/pint --test`.
- [ ] Run `APP_ENV=testing php artisan test`.
- [ ] Run Go format/vet/test and compose config/build backend.
- [ ] Push branch, open PR to `develop`, wait for CI, merge, deploy to `/opt/billing`, run migrations/seeds, and smoke `/admin/audit-logs`.
