# Sprint 19 Payment Expiry and Reconciliation Design

## Goal

Make wallet top-up payment intents expire deterministically and give finance admins a safe way to reconcile unmatched or late private-bank payments without automatic wallet credit.

## Scope

Sprint 19 adds:

- `php artisan payment-intents:expire` to mark stale pending payment intents as `expired`.
- Scheduler coverage for the expiry command through `scheduled-tasks:run payment_intents_expire`.
- Sandbox webhook and private bank sync handling for expired intents.
- Admin payment event statuses `expired` and `reconciled`.
- Manual reconciliation from `GET /admin/payment-events/{paymentEvent}` through `POST /admin/payment-events/{paymentEvent}/reconcile-wallet`.

## Non-Goals

This sprint does not add automatic reconciliation heuristics, provider dispute workflows, refunds, payout accounting, invoice settlement through late payment events, bank statement imports, or external accounting exports.

## Payment Intent Expiry

The expiry command selects `payment_intents` where:

- `status = pending`
- `expires_at` is not null
- `expires_at <= now()`

It updates them to `status = expired` and prints the number of expired rows. Running it repeatedly is safe because already-expired intents are skipped.

## Late Bank Payments

When a bank transaction matches an expired payment intent:

- A `payment_events` row is written with `status = expired`.
- The wallet is not credited automatically.
- The payment intent remains `expired`.
- Sandbox webhook returns an unprocessable response with `status = expired`.
- Private bank sync counts the transaction in an `expired` bucket.

Transactions with no matching reference remain `unmatched`. Transactions with wrong amount/currency or non-pending non-expired intents remain `rejected`.

## Admin Reconciliation

Finance users with `wallets.adjust` can reconcile a payment event when its status is `unmatched`, `rejected`, or `expired`.

The reconciliation form requires a customer email. The service:

- Validates that the event has positive `amount` and a `currency`.
- Finds or creates the customer's wallet in that currency.
- Credits the wallet using source type `payment_event_reconciliation`.
- Uses idempotency key `payment-event-reconcile:{payment_event_id}`.
- Updates the event to `status = reconciled` and links `wallet_id`.
- Adds reconciliation metadata to the event payload, including actor id and customer id.

Accepted or already-reconciled events cannot be reconciled again.

## Permissions

Existing permissions stay in place:

- Listing and viewing payment events requires `payment_events.view`.
- Reconciliation requires `wallets.adjust`.
- Finance and super admin roles can reconcile. Customers remain forbidden.

## Testing

Feature tests cover:

- The expiry command marks stale pending intents expired and leaves future/succeeded intents alone.
- Sandbox webhook records late expired-intent payments without wallet credit.
- Private bank sync records late expired-intent payments and reports the expired count.
- Admin reconciliation credits the selected customer wallet once and marks the event reconciled.
- Accepted events and customer users cannot reconcile events.
- The scheduled task registry exposes `payment_intents_expire`.
