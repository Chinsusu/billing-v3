# Sprint 2 Wallet Invoice Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build wallet balances, immutable ledger entries, invoices, bank-style QR payment intents, payment events, and a signed webhook sandbox.

**Architecture:** Keep finance behavior in focused Laravel models and services. Controllers stay thin, services own wallet mutations and webhook processing, and feature tests verify money movement and idempotency through HTTP flows.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests, Spatie permissions.

---

### Task 1: Finance Schema And Models

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_020000_create_finance_tables.php`
- Create: `apps/backend-laravel/app/Models/Wallet.php`
- Create: `apps/backend-laravel/app/Models/LedgerEntry.php`
- Create: `apps/backend-laravel/app/Models/Invoice.php`
- Create: `apps/backend-laravel/app/Models/PaymentIntent.php`
- Create: `apps/backend-laravel/app/Models/PaymentEvent.php`
- Create: `apps/backend-laravel/database/factories/WalletFactory.php`
- Create: `apps/backend-laravel/database/factories/InvoiceFactory.php`
- Create: `apps/backend-laravel/database/factories/PaymentIntentFactory.php`

- [x] Write failing model-backed feature tests that expect wallet, invoice, payment intent, payment event, and ledger tables.
- [x] Add the finance migration with UUID primary keys, foreign keys, unique idempotency keys, and useful indexes.
- [x] Add models with fillable attributes, casts, UUID support, and relationships.
- [x] Add factories used by feature tests.
- [x] Run the finance tests and verify the schema/model section passes.

### Task 2: Wallet Ledger Service

**Files:**
- Create: `apps/backend-laravel/app/Services/Finance/WalletService.php`
- Create: `apps/backend-laravel/app/Exceptions/InsufficientWalletBalance.php`
- Test: `apps/backend-laravel/tests/Feature/WalletTopUpFlowTest.php`
- Test: `apps/backend-laravel/tests/Feature/InvoicePaymentFlowTest.php`

- [x] Write failing tests for wallet credit idempotency, wallet debit, and insufficient balance.
- [x] Implement `WalletService::walletFor()`, `credit()`, and `debit()` with database transactions and row locks.
- [x] Make debit throw `InsufficientWalletBalance` when balance is too low.
- [x] Ensure duplicate idempotency keys return the existing ledger entry without changing balance.
- [x] Run targeted wallet/invoice tests and verify they pass.

### Task 3: Payment Intents And Bank Webhook Sandbox

**Files:**
- Create: `apps/backend-laravel/app/Services/Finance/PaymentIntentService.php`
- Create: `apps/backend-laravel/app/Services/Finance/BankWebhookProcessor.php`
- Create: `apps/backend-laravel/app/Http/Controllers/BankWebhookSandboxController.php`
- Modify: `apps/backend-laravel/config/services.php`
- Modify: `apps/backend-laravel/.env.example`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/WalletTopUpFlowTest.php`

- [x] Write failing tests for top-up intent creation, valid signed webhook, duplicate webhook, invalid signature, unknown reference, and amount mismatch.
- [x] Implement payment intent creation with stable reference and QR payload.
- [x] Implement HMAC signature verification using `BANK_SANDBOX_WEBHOOK_SECRET`.
- [x] Implement webhook processing statuses: `accepted`, `duplicate`, `unmatched`, and `rejected`.
- [x] Wire the public webhook route.
- [x] Run targeted wallet top-up tests and verify they pass.

### Task 4: Customer Wallet And Invoice Flows

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/WalletController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/WalletTopUpController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/InvoiceController.php`
- Create: `apps/backend-laravel/app/Services/Finance/InvoicePaymentService.php`
- Create: `apps/backend-laravel/resources/views/wallet/show.blade.php`
- Create: `apps/backend-laravel/resources/views/wallet/top-up.blade.php`
- Create: `apps/backend-laravel/resources/views/invoices/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/InvoicePaymentFlowTest.php`

- [x] Write failing tests for customer wallet page, own invoice view, paying invoice with wallet, insufficient balance, and invoice ownership protection.
- [x] Implement controllers and routes for `/wallet`, `/wallet/top-ups`, `/wallet/top-ups/{paymentIntent}`, `/invoices/{invoice}`, and `/invoices/{invoice}/pay`.
- [x] Implement invoice payment service using wallet debit and idempotency key `invoice-payment:{invoice_id}`.
- [x] Add Blade views with wallet balance, ledger rows, invoice details, and top-up QR payload.
- [x] Run targeted customer flow tests and verify they pass.

### Task 5: Admin Finance Views, Seeds, Docs, And Verification

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/InvoiceController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/PaymentEventController.php`
- Create: `apps/backend-laravel/app/Http/Requests/Admin/StoreInvoiceRequest.php`
- Create: `apps/backend-laravel/resources/views/admin/invoices/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/payment-events/index.blade.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `README.md`
- Test: `apps/backend-laravel/tests/Feature/AdminFinanceTest.php`

- [x] Write failing tests for admin invoice creation and payment event visibility.
- [x] Add invoice and payment event permissions to roles.
- [x] Implement admin controllers, request validation, routes, and views.
- [x] Seed a customer wallet and open invoice for the seeded customer.
- [x] Update README with Sprint 2 wallet, invoice, webhook, and signature examples.
- [x] Run all Laravel tests.
- [x] Run Pint.
- [x] Run Go worker checks.
- [x] Run docker compose config and secret scan.
- [x] Commit and push the feature branch.
