# Sprint 19 Payment Expiry and Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add deterministic payment intent expiry and safe manual bank payment reconciliation.

**Architecture:** Reuse existing payment intent, payment event, wallet, and ledger tables. Add one expiry command, one reconciliation service/controller, extend bank processors for expired intents, and surface the workflow on the existing admin payment event detail page.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests, existing scheduler wrapper.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-19-payment-reconciliation-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-19-payment-reconciliation.md`

- [x] Save S19 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/PaymentIntentExpiryReconciliationTest.php`

- [x] Add expiry command test for stale, future, and succeeded intents.
- [x] Add sandbox webhook test for late payment against an expired intent.
- [x] Add private bank sync test for late payment against an expired intent.
- [x] Add admin reconciliation success and idempotency test.
- [x] Add admin reconciliation forbidden/invalid-status tests.
- [x] Add scheduled task registry assertion.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Expiry Command and Scheduler

**Files:**
- Create: `apps/backend-laravel/app/Console/Commands/ExpirePaymentIntentsCommand.php`
- Modify: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Modify: `apps/backend-laravel/routes/console.php`

- [x] Implement `payment-intents:expire`.
- [x] Register `payment_intents_expire` as an allowed scheduled task.
- [x] Schedule it every minute through the existing scheduler wrapper.
- [x] Run targeted test until expiry and scheduler assertions pass.
- [x] Commit expiry command slice.

### Task 4: Expired Bank Event Handling

**Files:**
- Modify: `apps/backend-laravel/app/Services/Finance/BankWebhookProcessor.php`
- Modify: `apps/backend-laravel/app/Services/Finance/PrivateBankTransactionProcessor.php`
- Modify: `apps/backend-laravel/app/Console/Commands/SyncPrivateBankPaymentsCommand.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/PaymentEventController.php`
- Modify: `apps/backend-laravel/resources/views/admin/payment-events/index.blade.php`

- [x] Record matching expired-intent transactions as `expired`.
- [x] Avoid wallet credit for expired transactions.
- [x] Keep amount/currency mismatches as `rejected`.
- [x] Add `expired` count to private bank sync output.
- [x] Add `expired` and `reconciled` to admin filters.
- [x] Run targeted test until expired bank event assertions pass.
- [x] Commit expired bank event slice.

### Task 5: Admin Reconciliation

**Files:**
- Create: `apps/backend-laravel/app/Services/Finance/PaymentEventReconciliationService.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/PaymentEventReconciliationController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/payment-events/show.blade.php`

- [x] Implement reconciliation service with wallet credit idempotency.
- [x] Implement admin POST controller validating `user_email`.
- [x] Protect route with `wallets.adjust`.
- [x] Add reconciliation form for `unmatched`, `rejected`, and `expired` events.
- [x] Render reconciliation metadata for reconciled events.
- [x] Run targeted test until reconciliation assertions pass.
- [x] Commit reconciliation slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-19-payment-reconciliation.md`

- [x] Update README with S19 routes and commands.
- [x] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Verify `/admin/payment-events`, `/admin/ops-health`, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
