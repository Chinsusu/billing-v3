# Sprint 24 Provider Callback Intake Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add signed provider callback intake and reconcile asynchronous provider action status back into services.

**Architecture:** Extend provider account configuration with callback secret and JSON paths, add an audited callback event model/table, implement one webhook controller and one processor service, and show callback history in the existing admin service runbook.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-24-provider-callbacks-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-24-provider-callbacks.md`

- [x] Save S24 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProviderCallbackIntakeTest.php`

- [x] Add valid signed cancel callback reconciliation test.
- [x] Add duplicate provider event id idempotency assertion.
- [x] Add invalid signature rejection test.
- [x] Add unmatched callback audit test.
- [x] Add admin callback config write-only secret test.
- [x] Add admin service runbook callback history test.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Schema and Provider Account Config

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_160000_add_provider_callback_config_to_provider_accounts.php`
- Create: `apps/backend-laravel/database/migrations/2026_05_24_161000_create_provider_callback_events_table.php`
- Modify: `apps/backend-laravel/app/Models/ProvisioningProviderAccount.php`
- Create: `apps/backend-laravel/app/Models/ProviderCallbackEvent.php`
- Modify: `apps/backend-laravel/app/Http/Requests/StoreProvisioningProviderAccountRequest.php`
- Modify: `apps/backend-laravel/app/Http/Requests/UpdateProvisioningProviderAccountRequest.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningProviderAccountController.php`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/_form.blade.php`

- [x] Add callback config columns and encrypted casts.
- [x] Add provider callback event table and model relationships.
- [x] Persist callback config from admin create/update.
- [x] Keep callback secret write-only in the form.
- [x] Run targeted test until config assertions pass.
- [x] Commit schema/config slice.

### Task 4: Callback Webhook and Reconciliation

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/ProviderCallbackController.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderCallbackProcessor.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/app/Models/Service.php`

- [x] Add signed webhook route and CSRF exception.
- [x] Validate provider enabled/configured/signature.
- [x] Extract callback fields through provider account JSON paths.
- [x] Persist redacted callback audit rows.
- [x] Reconcile service status, provider action job, and linked cancellation rows.
- [x] Enforce duplicate provider event id idempotency.
- [x] Run targeted test until webhook assertions pass.
- [x] Commit webhook/reconciliation slice.

### Task 5: Admin Runbook, Documentation, Verification, PR, Deploy

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceController.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/show.blade.php`
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-24-provider-callbacks.md`

- [x] Load provider callback events on admin service runbook.
- [x] Render callback history without exposing raw secrets.
- [x] Update README with S24 endpoint/config behavior.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify webhook route, `/admin/services`, and `/up` smoke behavior.
- [x] Mark verification steps complete in this plan, commit, and push.
- [x] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
