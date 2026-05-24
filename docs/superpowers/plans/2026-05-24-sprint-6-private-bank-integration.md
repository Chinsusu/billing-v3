# Sprint 6 Private Bank Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-managed encrypted private bank configuration and backend payment sync for wallet top-ups.

**Architecture:** Laravel stores bank integration config in a dedicated model with encrypted secret casts. Admin controllers own CRUD/test-connection flows. Finance services own private bank HTTP fetching and transaction application so sandbox webhook behavior can stay in place.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Laravel HTTP client, encrypted Eloquent casts, PHPUnit feature tests.

---

### Task 1: S6 Documentation

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-6-private-bank-integration-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-6-private-bank-integration.md`

- [x] Save the approved S6 design.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders and contradictions.
- [ ] Commit documentation before code.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/BankIntegrationAdminTest.php`
- Create: `apps/backend-laravel/tests/Feature/PrivateBankPaymentSyncTest.php`

- [ ] Add admin config tests for create/update/masked secrets/customer forbidden.
- [ ] Add test connection test with `Http::fake()`.
- [ ] Add payment sync tests for accepted, duplicate, unmatched, and rejected transactions.
- [ ] Run both new test files on the dev server and verify they fail on missing table, routes, model, and command.

Expected RED command:

```bash
docker run --rm --entrypoint sh -v /opt/billing/apps/backend-laravel:/app -w /app composer:2 -lc 'composer install --no-interaction --prefer-dist >/tmp/composer-install.log && php artisan test tests/Feature/BankIntegrationAdminTest.php tests/Feature/PrivateBankPaymentSyncTest.php'
```

### Task 3: Bank Integration Storage And Permissions

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_060000_create_bank_integrations_table.php`
- Create: `apps/backend-laravel/app/Models/BankIntegration.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`

- [ ] Add `bank_integrations` table with encrypted secret columns and audit fields.
- [ ] Add `BankIntegration` model with `api_key` and `webhook_secret` encrypted casts.
- [ ] Add `bank_integrations.manage` permission to `super_admin` and `finance`.

### Task 4: Admin UI And Test Connection

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/BankIntegrationController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/BankIntegrationTestController.php`
- Create: `apps/backend-laravel/app/Services/Finance/BankIntegrationService.php`
- Create: `apps/backend-laravel/app/Services/Finance/PrivateBankClient.php`
- Create: `apps/backend-laravel/resources/views/admin/bank-integrations/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/bank-integrations/create.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/bank-integrations/edit.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/bank-integrations/_form.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Implement CRUD routes under `/admin/bank-integrations`.
- [ ] Store/rotate secrets via service; blank secret input preserves current value.
- [ ] Mask configured secrets in views.
- [ ] Implement `POST /admin/bank-integrations/{bankIntegration}/test`.
- [ ] Run admin config tests and fix failures.

### Task 5: Private Bank Payment Sync

**Files:**
- Create: `apps/backend-laravel/app/Services/Finance/PrivateBankTransactionProcessor.php`
- Create: `apps/backend-laravel/app/Console/Commands/SyncPrivateBankPaymentsCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`

- [ ] Implement private bank transaction matching by `reference`.
- [ ] Record `payment_events` with provider `private_bank`.
- [ ] Credit wallet through `WalletService` with idempotency key `private_bank:{transaction_id}`.
- [ ] Mark matched payment intents `succeeded`.
- [ ] Register `bank:sync-payments` command.
- [ ] Run payment sync tests and fix failures.

### Task 6: Docs And Verification

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-6-private-bank-integration.md`

- [ ] Document S6 admin routes and sync command.
- [ ] Mark plan checklist complete after verification.
- [ ] Run Laravel Pint and full tests on the dev server.
- [ ] Run Go `gofmt`, `go vet`, and `go test`.
- [ ] Run Docker compose config and secret scan.
- [ ] Commit, push, open PR to `develop`, wait for CI, merge, and update `/opt/billing`.
