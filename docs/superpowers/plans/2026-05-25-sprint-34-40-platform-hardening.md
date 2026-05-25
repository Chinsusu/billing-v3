# Sprint 34-40 Platform Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete S34-S40 as one epic: MFA/session security, impersonation, support tickets, API keys, reseller pricing, finance exports, and production hardening.

**Architecture:** Keep each sprint in a focused vertical slice: migrations/models, thin controllers, Blade views, route registration, audit logging, and feature tests. Shared helpers are limited to MFA TOTP validation, API key verification, and reseller price resolution. Existing Spatie permissions and `AuditLogger` stay the security and audit backbone.

**Tech Stack:** Laravel 13, Blade, Spatie Laravel Permission, database sessions, PHPUnit feature tests, existing Docker/CI stack.

---

### Task 1: S34 MFA and Sessions

**Files:**
- Create: `apps/backend-laravel/app/Services/Security/TotpService.php`
- Create: `apps/backend-laravel/app/Http/Controllers/MfaController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/UserMfaController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/UserSessionController.php`
- Create: `apps/backend-laravel/app/Http/Middleware/EnsureMfaIsVerified.php`
- Create: `apps/backend-laravel/database/migrations/2026_05_25_140000_add_mfa_fields_to_users_table.php`
- Modify: `apps/backend-laravel/app/Models/User.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/users/show.blade.php`
- Test: `apps/backend-laravel/tests/Feature/MfaSessionSecurityTest.php`

- [ ] Write failing MFA/session tests.
- [ ] Verify RED on server.
- [ ] Implement TOTP, recovery codes, MFA route gate, admin MFA actions, and session revocation.
- [ ] Run targeted tests and commit.

### Task 2: S35 Impersonation

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/CustomerImpersonationController.php`
- Modify: `apps/backend-laravel/resources/views/admin/customers/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/AdminImpersonationTest.php`

- [ ] Write failing impersonation tests.
- [ ] Verify RED on server.
- [ ] Implement impersonation start/stop, banner, admin-target guard, and audit.
- [ ] Run targeted tests and commit.

### Task 3: S36 Support Tickets

**Files:**
- Create: `apps/backend-laravel/app/Models/SupportTicket.php`
- Create: `apps/backend-laravel/app/Models/SupportTicketNote.php`
- Create: `apps/backend-laravel/app/Http/Controllers/SupportTicketController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/SupportTicketController.php`
- Create: support ticket Blade views under `resources/views/support` and `resources/views/admin/support-tickets`
- Create: support ticket migrations
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/SupportTicketTest.php`

- [ ] Write failing support ticket tests.
- [ ] Verify RED on server.
- [ ] Implement customer/admin ticket flows, notes, permissions, and audit.
- [ ] Run targeted tests and commit.

### Task 4: S37 API Keys

**Files:**
- Create: `apps/backend-laravel/app/Models/ApiKey.php`
- Create: `apps/backend-laravel/app/Services/Security/ApiKeyManager.php`
- Create: `apps/backend-laravel/app/Http/Middleware/AuthenticateApiKey.php`
- Create: `apps/backend-laravel/app/Http/Controllers/ApiKeyController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Api/MeController.php`
- Create: API key migration and Blade views
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/ApiKeyManagementTest.php`

- [ ] Write failing API key tests.
- [ ] Verify RED on server.
- [ ] Implement hashed API keys, customer UI, bearer middleware, and `/api/v1/me`.
- [ ] Run targeted tests and commit.

### Task 5: S38 Reseller Foundation

**Files:**
- Create: `apps/backend-laravel/app/Models/ResellerPriceOverride.php`
- Create: `apps/backend-laravel/app/Services/Resellers/ResellerPricingService.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/CustomerResellerController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ResellerPriceOverrideController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/ResellerCustomerController.php`
- Create: reseller migrations and Blade views
- Modify: `apps/backend-laravel/app/Models/User.php`
- Modify: product catalog, checkout, renewal pricing
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/ResellerFoundationTest.php`

- [ ] Write failing reseller ownership and pricing tests.
- [ ] Verify RED on server.
- [ ] Implement customer assignment, reseller customer list, price overrides, checkout and renewal pricing.
- [ ] Run targeted tests and commit.

### Task 6: S39 Billing Exports

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/BillingReportController.php`
- Create: `apps/backend-laravel/app/Services/Reports/CsvResponseFactory.php`
- Create: report Blade view
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/BillingExportReportTest.php`

- [ ] Write failing CSV export tests.
- [ ] Verify RED on server.
- [ ] Implement report screen, invoice/ledger/payment CSV exports, permissions, and audit.
- [ ] Run targeted tests and commit.

### Task 7: S40 Production Hardening

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/ReadinessController.php`
- Create: `apps/backend-laravel/app/Console/Commands/PurgeAuditLogsCommand.php`
- Create: `apps/backend-laravel/config/ops.php`
- Create: `docs/operations/production-runbook.md`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `README.md`
- Test: `apps/backend-laravel/tests/Feature/ProductionHardeningTest.php`

- [ ] Write failing readiness/retention tests.
- [ ] Verify RED on server.
- [ ] Implement readiness endpoint, audit retention command, config, and runbook docs.
- [ ] Run targeted tests and commit.

### Task 8: Full Verification, PR, Merge, Deploy

- [ ] Run targeted tests for all new sprint feature tests.
- [ ] Run full Laravel Pint and PHPUnit on server.
- [ ] Run Go worker tests.
- [ ] Run Compose config/build.
- [ ] Run secret scan and `git diff --check`.
- [ ] Open PR, wait for CI, merge to `develop`, deploy to `/opt/billing`, run migrations and smoke routes.
