# Sprint 28 Auto-Renewal Foundation Design

## Goal

Add a safe auto-renewal foundation for services that already support manual renewal. Customers can opt in per service, the scheduler renews due services from wallet balance, failures are tracked and notified, and admins can inspect the latest auto-renewal state.

## Scope

Included:

- Per-service auto-renew toggle for customers.
- Scheduled command that renews active opted-in services expiring within the configured due window.
- Durable auto-renewal attempt history with idempotency, status, retry time, and last error.
- Wallet-funded renewal through the existing `ServiceRenewalService`.
- Customer notification when auto-renew fails.
- Scheduler registration and ops health freshness checks.
- Customer and admin service detail visibility.

Out of scope:

- Invoice reservation before renewal.
- Card/bank direct debit.
- Provider-specific renewal logic beyond the existing provider action abstraction.
- Admin-forced auto-renew on behalf of customers.

## Data Model

Add columns to `services`:

- `auto_renew_enabled` boolean, default false.

Add `service_auto_renewal_attempts`:

- `id` UUID primary key.
- `service_id` foreign key cascade.
- `user_id` nullable foreign key with null on delete.
- `expires_at` timestamp for the service expiry this attempt targets.
- `status`: `processing`, `succeeded`, `failed`, or `skipped`.
- `attempts` unsigned integer.
- `next_attempt_at` nullable timestamp for retry backoff.
- `renewed_expires_at` nullable timestamp.
- `amount` nullable unsigned big integer.
- `currency` nullable 3-character string.
- `last_error` nullable text.
- `idempotency_key` unique string.
- timestamps.

There is one logical attempt row per service and target expiry. Re-runs update the same row instead of duplicating ledger, wallet, provider, or notification side effects.

## Scheduler Behavior

Command: `services:auto-renew --limit=50`.

The command selects active services where:

- `auto_renew_enabled = true`.
- `expires_at` exists.
- `expires_at <= now() + 1 day`.
- There is no open service cancellation request with a non-final status.

For each candidate:

1. Create or reuse the attempt row keyed by service id and target expiry.
2. Skip rows already succeeded.
3. Skip failed rows whose `next_attempt_at` is still in the future.
4. Mark the row `processing`, increment `attempts`, and call `ServiceRenewalService::renew()`.
5. On success, mark `succeeded`, store the new expiry and price snapshot.
6. On failure, mark `failed`, store the error, set `next_attempt_at` one hour later, and enqueue one failure notification for that attempt number.

Manual renewal remains available and continues to use the existing wallet and provider idempotency key. Auto-renew only orchestrates when that renewal is attempted.

## Notifications

Add customer template type `service_auto_renew_failed` with variables:

- `service_id`
- `product_name`
- `expires_at`
- `next_attempt_at`
- `error`

The notification uses an idempotency key containing the attempt id and attempt count, so immediate repeated command runs do not spam the customer.

## UI and Permissions

Customer service detail:

- Shows auto-renew enabled/disabled state.
- Lets the service owner enable or disable auto-renew while the service is active.
- Shows the latest attempt status/error when available.

Admin service runbook:

- Shows auto-renew enabled/disabled.
- Shows attempt history with status, attempts, target expiry, renewed expiry, next retry, and error.

No new admin permission is required; existing `services.view` covers read-only runbook visibility.

## Testing

Feature tests cover:

1. A customer can enable and disable auto-renew for their own active service.
2. A customer cannot toggle another customer's service.
3. The command renews due opted-in services, debits wallet once, records a succeeded attempt, and does not renew non-opted-in or future services.
4. Insufficient wallet balance records a failed attempt, sets retry time, enqueues `service_auto_renew_failed`, and immediate re-run does not duplicate the notification.
5. Open scheduled cancellation prevents auto-renewal.
6. Scheduler registry, Laravel schedule, and ops health include the auto-renew task.

Verification:

- Targeted auto-renew feature tests.
- Scheduler and ops health tests.
- Notification template/preference tests.
- Full `php artisan test`.
- Pint.
- Existing Go and Compose CI checks.
