# Sprint 5 Service Renewal Expiry Design

## Context

Sprint 4 made provisioning execution usable: paid orders now create services, and the Go worker can activate them through the sandbox provider. The next missing lifecycle step is renewal and expiry. Without it, active services have an `expires_at` timestamp but customers cannot extend it and the system does not transition overdue services out of `active`.

Sprint 5 keeps the platform provider-neutral. It adds internal lifecycle accounting and status automation before real provider renew/suspend API calls are introduced.

## Scope

Build service renewal and expiry automation:

- Customers can renew their own `active` services from the service detail page.
- Renewal debits the customer's wallet for the service's current product price.
- Renewal extends `expires_at` by the service duration, using the later of `now()` or the current `expires_at` as the base date.
- Renewal writes an immutable ledger entry with idempotency key `service-renewal:{service_id}:{old_expires_at}`.
- Renewal records audit metadata on the service.
- Customers cannot renew another user's service.
- Services that are already `expired`, `cancelled`, or not yet active cannot be renewed.
- An Artisan command marks overdue `active` services as `expired`.
- Admin service listings and customer service detail expose lifecycle status clearly.

Sprint 5 does not call provider renew/suspend APIs, auto-charge customers, send email notifications, create renewal invoices, refund unused time, or implement scheduled daemon deployment. Those are later provider and billing automation sprints.

## Domain Model

The existing `services` table is sufficient. Sprint 5 uses:

- `services.status`: includes `active` and `expired`.
- `services.expires_at`: lifecycle deadline.
- `services.meta.duration_days`: service duration copied at checkout.
- `services.meta.renewals`: append-only array of renewal audit records.
- `ledger_entries`: renewal debits with `source_type=service_renewal`, `source_id={service_id}`, and idempotency key `service-renewal:{service_id}:{old_expires_at}`.

The renewal price comes from the current linked `products.price_amount` when available. If the product was archived or deleted, the service falls back to the original `order_items.unit_amount` snapshot. Currency comes from the same source and must match the customer's wallet currency.

## Flow

1. Customer opens `GET /services/{service}`.
2. Active services show a `Renew` action with the renewal amount and duration.
3. Customer submits `POST /services/{service}/renew`.
4. The backend owner-scopes the service and locks it inside a database transaction.
5. The backend rejects non-active services and services without an expiry timestamp.
6. The backend calculates the renewal base date as `max(now(), service.expires_at)`.
7. Wallet debit runs through `WalletService` with idempotency key `service-renewal:{service_id}:{old_expires_at}`.
8. The service `expires_at` is extended by `duration_days`.
9. The service `meta.renewals` array gets one audit record containing old expiry, new expiry, amount, currency, and timestamp.
10. The customer is redirected back to the service detail page.

Expiry automation is explicit:

1. Operator runs `php artisan services:expire`.
2. The command finds `active` services with `expires_at <= now()`.
3. It marks them `expired` and stores `expired_at` in `meta`.
4. It prints the number of expired services.

## Access Control

- Customers can renew only their own services.
- Unauthorized service access remains a 404, matching the existing service detail behavior.
- Admin users can view service statuses through the existing admin service list.
- The expiry command is CLI-only in Sprint 5 and is not exposed as an HTTP route.

## Error Handling

- Insufficient wallet balance redirects back with validation errors and leaves the service unchanged.
- Renewing inactive, expired, cancelled, or pending-provision services redirects back with validation errors.
- Missing duration or expiry data redirects back with validation errors.
- Duplicate form submissions reuse the same ledger idempotency key for the same old expiry and do not double debit.
- Expiry command is idempotent: running it repeatedly after expiration does not mutate already-expired services.

## Testing

Laravel feature tests cover:

- Customer renewing an active service extends expiry and debits wallet.
- Renewal uses current product price when the product exists.
- Renewal falls back to order item snapshot price when the product is missing.
- Insufficient balance leaves wallet and service expiry unchanged.
- Customers cannot renew another user's service.
- Expired services cannot be renewed.
- `services:expire` marks overdue active services expired and leaves future services active.

No Go worker changes are required in Sprint 5 because provider renew/suspend calls are deliberately out of scope.
