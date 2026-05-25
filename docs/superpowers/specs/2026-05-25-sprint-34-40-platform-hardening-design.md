# Sprint 34-40 Platform Hardening Design

## Context

Sprint 33 secured operator accounts with disable/enable, setup links, forced password reset, and login audit. S34-S40 hardens operator access, adds support operations, exposes scoped customer API keys, introduces reseller ownership and pricing, adds finance exports, and documents operational recovery paths.

## Sprint 34: MFA and Session Security

- Store MFA state on `users`: encrypted TOTP secret, enabled timestamp, required timestamp, and encrypted hashed recovery codes.
- Add authenticated routes for MFA setup, challenge, recovery-code challenge, and recovery-code regeneration.
- Gate normal authenticated pages with an MFA middleware when MFA is enabled or required.
- Let admins require or reset MFA from the user security panel.
- Let admins revoke database sessions for a user from the user detail screen.
- Audit MFA enable/disable/reset/required and session revoke actions.

TOTP is implemented locally with HMAC-SHA1 and 30-second windows to avoid a new dependency. Recovery codes are shown once and stored hashed inside an encrypted cast.

## Sprint 35: Admin Impersonation

- Admins with `customers.view` can impersonate non-admin customers from the customer detail page.
- The session stores the original admin ID and email, logs in as the target customer, and shows a banner until stopped.
- Admin routes remain protected by `admin.access`, so impersonated customer sessions cannot continue operating admin tools.
- Start and stop actions are audited.

## Sprint 36: Support Tickets and Internal Notes

- Add support ticket and note tables.
- Customers can open tickets, view their tickets, and add customer-visible notes.
- Admin/support operators can list all tickets, add internal or customer-visible notes, assign tickets, and update status.
- Add `support_tickets.view` and `support_tickets.manage`; grant support role both, super admin all, ops admin view/manage.
- Ticket mutations are audited.

## Sprint 37: API Key Management

- Customers can create and revoke API keys from a UI.
- Keys are stored as SHA-256 hashes with a visible prefix and scopes; plaintext is shown only once.
- Add bearer-key middleware and a minimal `GET /api/v1/me` endpoint scoped by `account.read`.
- API key usage updates `last_used_at` and revoked keys are rejected.
- Key create/revoke events are audited.

## Sprint 38: Reseller Foundation

- Users can belong to a reseller via `reseller_id`; reseller users can see assigned customers.
- Admins can assign customers to reseller users from the customer detail page.
- Reseller-specific product price overrides are stored in `reseller_price_overrides`.
- Product catalog, checkout, and renewal pricing resolve reseller override price for reseller-owned customers.
- Assignment and price override changes are audited.

## Sprint 39: Billing Exports and Reports

- Add admin billing report screen with date filters and export links.
- Export invoices, ledger entries, and payment events as CSV.
- Exports require existing finance-facing permissions: `invoices.view`, `payment_events.view`, or `wallets.adjust`.
- Export events are audited without storing row payloads.

## Sprint 40: Production Hardening

- Add a readiness endpoint that checks database connectivity and returns JSON.
- Add an audit log retention command with configurable `OPS_AUDIT_LOG_RETENTION_DAYS`.
- Document backup, restore, rollback, smoke, and retention runbooks.
- Add tests for readiness and retention behavior.

## Testing

Each sprint gets focused feature tests. Full verification remains Laravel Pint, full Laravel PHPUnit, Go worker tests, Compose config/build, secret scan, and `git diff --check`.
