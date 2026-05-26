# Sprint 22 Notification Outbox Design

Sprint 22 adds durable customer/admin notifications for billing and provisioning lifecycle events. The platform already records payment, provisioning, cancellation, provider callback, and ops state, but users and operators currently need to poll admin pages to discover important changes.

## Goals

- Persist notification events before delivery so sending is retryable and auditable.
- Send email through Laravel mail configuration, using Mailpit in the dev runtime.
- Let admins inspect notification delivery status and retry failed notifications.
- Queue notifications from existing lifecycle flows without changing their primary success behavior.
- Keep templates simple and code-owned for this sprint.

## Non-Goals

- User notification preference UI.
- SMS, Telegram, Slack, or webhook notification channels.
- Rich HTML template design.
- Marketing/bulk broadcast campaigns.
- Per-tenant branding.
- Replacing ops alert webhook delivery.

## Data Model

Add `notification_events`:

- `id` UUID primary key.
- `user_id` nullable FK.
- `channel`: `email`.
- `type`: event type such as `wallet_credited`, `invoice_paid`, `service_provisioned`.
- `recipient_email`, `subject`, `body_text`.
- `source_type`, `source_id`: origin record.
- `idempotency_key`: unique delivery identity.
- `status`: `pending`, `sending`, `sent`, or `failed`.
- `attempts`, `max_attempts`.
- `available_at`, `sent_at`.
- `last_error`.
- `payload` JSON for audit context.
- timestamps.

The outbox row is the audit record. It stores rendered subject/body so later template edits do not rewrite historical notifications.

## Enqueue Contract

`NotificationOutbox` exposes one idempotent enqueue method:

```php
enqueue(
    ?User $user,
    string $type,
    string $recipientEmail,
    string $subject,
    string $body,
    string $sourceType,
    ?string $sourceId,
    string $idempotencyKey,
    array $payload = []
): NotificationEvent
```

If the idempotency key already exists, the existing event is returned and no duplicate row is created.

## Delivery

`php artisan notifications:send --limit=50` claims pending events whose `available_at <= now()` using row locks, marks each row `sending`, and sends `body_text` via `Mail::raw`.

On success:

- `status=sent`
- `sent_at=now()`
- `last_error=null`

On failure:

- `attempts` is already incremented when claimed.
- If `attempts < max_attempts`, set `status=pending`, `available_at=now()+60 seconds`, and store `last_error`.
- If attempts are exhausted, set `status=failed` and store `last_error`.

Admin retry sets failed rows back to `pending`, clears `last_error`, and sets `available_at=now()` without resetting attempts.

## Event Triggers

Sprint 22 queues these notifications:

- `wallet_credited`: accepted bank webhook and manual payment-event reconciliation.
- `invoice_paid`: customer pays an invoice from wallet.
- `service_renewed`: customer renews a service.
- `service_cancellation_requested`: customer requests immediate or period-end cancellation.
- `service_cancellation_completed`: local scheduled cancellation, provider action completion, and provider callback completion.
- `provider_action_failed`: provider action job reaches final failed state.
- `provider_callback_unmatched`: signed provider callback cannot match a service.
- `service_expiry_warning`: active service expires within 72 hours; queued by the notification send command before delivery.
- `service_provisioned`: Go worker marks a service active after successful provisioning.

All triggers use idempotency keys tied to the source record and milestone, for example `invoice-paid:{invoice_id}` or `service-expiry-warning:{service_id}:{expires_at_iso}`.

## Admin UI

Add admin routes:

- `GET /admin/notification-events`
- `GET /admin/notification-events/{notificationEvent}`
- `POST /admin/notification-events/{notificationEvent}/retry`

The index supports status, type, and recipient filters. Detail shows source metadata, payload JSON, attempts, last error, and rendered email body. Raw secrets should not be placed into notification payloads.

Access uses a new `notifications.manage` permission. `super_admin` and `ops_admin` receive it.

## Scheduler and Ops Health

Register scheduled task `notifications_send` mapped to `notifications:send --limit=50`. Run it every minute and include it in ops health freshness checks.

## Testing

Laravel tests cover:

- Wallet credit and invoice payment enqueue idempotent notifications.
- Notification send command marks sent events and records failures/retries.
- Service expiry warnings are queued once and sent.
- Admin can list, inspect, and retry failed notifications.
- Provider callback unmatched and provider action final failure enqueue operator notifications.
- Scheduler registry includes `notifications_send`.

Go tests cover:

- `MarkProcessed` inserts a `service_provisioned` notification event with stable idempotency.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Rebuild backend, scheduler, and worker.
3. Run migrations.
4. Verify `php artisan notifications:send --limit=1` exits cleanly.
5. Smoke `/admin/notification-events`, `/admin/ops-health`, and `/up`.
