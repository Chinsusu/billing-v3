# Sprint 18 Customer Billing Activity Design

## Goal

Make the customer portal usable for repeated account operations by adding customer-owned order, invoice, and top-up history pages plus a more useful dashboard snapshot.

## Scope

Sprint 18 adds:

- Customer order index at `/orders`.
- Customer invoice index at `/invoices`.
- Customer wallet top-up index at `/wallet/top-ups`.
- Dashboard counts and recent activity links for invoices, orders, services, wallet, and top-ups.
- Navigation links for Orders and Invoices.

Existing detail routes remain authoritative:

- `/orders/{order}`
- `/invoices/{invoice}`
- `/wallet/top-ups/{paymentIntent}`

## Non-Goals

This sprint does not add refunds, downloadable PDFs, notifications, provider runbooks, bank reconciliation, or new payment methods.

## Authorization

Every new index must scope rows to the authenticated user. Existing detail routes already return 404 for another user's records and remain unchanged.

## UI

Use the current Blade panel/table style. Customer-facing pages should hide provider internals and focus on:

- Reference number.
- Status.
- Amount.
- Date.
- Link to detail/action.

## Testing

Feature tests cover:

- Customer can list only their own orders.
- Customer can list only their own invoices.
- Customer can list only their own wallet top-up intents.
- Dashboard shows counts, recent records, and links.
- Guests are redirected from new customer history pages.
