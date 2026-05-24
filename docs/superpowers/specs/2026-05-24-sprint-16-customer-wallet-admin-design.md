# Sprint 16 Customer Wallet Admin Design

## Goal

Give support and finance operators a simple admin surface for finding customers, inspecting account state, and applying audited manual wallet adjustments.

## Scope

Sprint 16 adds:

- Admin customer index with search by name/email.
- Admin customer detail with wallet balances, recent ledger entries, invoices, orders, and services.
- Manual wallet credit/debit form for authorized finance/admin users.
- Ledger-backed audit metadata for actor, reason, reference, and direction.
- RBAC permissions for customer viewing and wallet adjustment.

## Non-Goals

This sprint does not add impersonation, customer profile editing, KYC, refunds to bank accounts, bulk imports, or a separate wallet adjustment table. `ledger_entries` is the authoritative audit record for wallet movement.

## Permissions

- `customers.view`: access `/admin/customers` and `/admin/customers/{user}`.
- `wallets.adjust`: post manual wallet credit/debit adjustments.

`super_admin` gets both permissions. `finance` gets both. `support` gets customer viewing only.

## Routes

- `GET /admin/customers`
- `GET /admin/customers/{user}`
- `POST /admin/customers/{user}/wallet-adjustments`

All routes remain inside the existing `auth`, `admin.access`, and `admin.` route group.

## Wallet Adjustment Behavior

The adjustment form accepts:

- `direction`: `credit` or `debit`.
- `amount`: positive integer minor-unit amount.
- `currency`: three-letter currency, default `VND`.
- `reason`: required operator explanation.
- `reference`: optional operator reference.

Implementation reuses `WalletService` for locking, currency validation, non-negative balances, idempotency, and ledger creation.

Idempotency key:

- With reference: `admin-wallet-adjustment:{user_id}:{currency}:{reference}`.
- Without reference: `admin-wallet-adjustment:{uuid}`.

Ledger entry:

- `source_type`: `admin_wallet_adjustment`.
- `source_id`: `null`.
- `description`: reason.
- `meta`: actor id/email, direction, reason, and reference.

If a debit would overdraw the wallet, the request redirects back with validation errors and no ledger entry is created.

## UI

The admin dashboard adds a `Customers` link. Customer views use the current Blade panel/table style. The adjustment form appears only for users with `wallets.adjust`.

## Testing

Feature tests cover:

- Admin can search and view customers with wallet/account context.
- Finance user can credit and debit a wallet, with ledger audit metadata.
- Duplicate adjustment reference does not double-apply balance movement.
- Overdraw debit is rejected without mutation.
- Support can view customers but cannot adjust wallets.
- Customer cannot access admin customer pages.
