# Sprint 15 Ops Alerts Design

Sprint 15 turns the S14 Ops Health page into proactive alerting. The system will evaluate scheduler and queue health on a schedule, persist alert events, and optionally notify an external webhook endpoint configured by an admin.

## Goals

- Detect unhealthy Ops Health signals without requiring an admin to open `/admin/ops-health`.
- Persist alert history with enough context to debug the issue.
- Avoid alert spam through fingerprint-based cooldowns.
- Let admins acknowledge and resolve alert events from the admin UI.
- Keep notification delivery generic through webhook channels only in this sprint.

## Non-Goals

- Telegram, email, SMS, Slack-specific formatting, or user-facing notifications.
- Complex rule builder expressions.
- Background queue workers for alert delivery.
- Pager escalation policies.

## Approach

Laravel remains the control plane because Ops Health already reads database-backed scheduler, queue, service, and bank state. S15 adds an `ops_alert_rules` table for channel configuration and cooldown policy, plus `ops_alert_events` for alert lifecycle and delivery results.

The first rule type is `ops_health`. It evaluates the existing `OpsHealthSnapshot` output and creates one event per unhealthy signal:

- scheduled task `warning` or `failed`
- provisioning queue `warning` or `failed`
- provider action queue `warning` or `failed`
- overdue active services count greater than zero
- enabled bank integration count equal to zero

Each event has a stable `fingerprint` such as `task:bank_sync_payments:warning` or `queue:provider_action:failed`. When a matching unresolved event exists inside the rule cooldown window, the evaluator skips creating a duplicate.

## Data Model

`ops_alert_rules`

- `id` UUID primary key.
- `name` string.
- `type` string, initially `ops_health`.
- `enabled` boolean.
- `severity` string default `warning`.
- `cooldown_minutes` unsigned integer default `15`.
- `webhook_url` encrypted nullable string.
- `webhook_secret` encrypted nullable string.
- `last_evaluated_at` timestamp nullable.
- timestamps.

`ops_alert_events`

- `id` UUID primary key.
- `ops_alert_rule_id` nullable foreign key, null on rule delete.
- `fingerprint` string.
- `severity` string.
- `status` string: `open`, `acknowledged`, `resolved`.
- `title` string.
- `message` text.
- `context` JSON.
- `first_seen_at`, `last_seen_at` timestamps.
- `acknowledged_at`, `resolved_at` timestamps nullable.
- `acknowledged_by_id`, `resolved_by_id` nullable user references.
- `delivery_status` string nullable: `skipped`, `delivered`, `failed`.
- `delivery_error` text nullable.
- timestamps.

## Admin UI

Routes under the existing admin group:

- `GET /admin/ops-alert-rules`
- `POST /admin/ops-alert-rules`
- `PUT /admin/ops-alert-rules/{opsAlertRule}`
- `GET /admin/ops-alert-events`
- `POST /admin/ops-alert-events/{opsAlertEvent}/acknowledge`
- `POST /admin/ops-alert-events/{opsAlertEvent}/resolve`

Use `provisioning_jobs.view` for read/action access in this sprint, matching `/admin/ops-health`. Webhook secrets are never rendered back to the browser.

The admin dashboard gains an `Ops Alerts` link.

## Evaluation And Delivery

Command:

- `php artisan ops-alerts:evaluate`

The command evaluates enabled `ops_health` rules. For each emitted alert candidate:

1. Build a stable fingerprint.
2. Find latest unresolved matching event for the rule.
3. If it exists and `last_seen_at` is newer than cooldown cutoff, update `last_seen_at` and skip delivery.
4. Otherwise create a new `open` event.
5. If the rule has a webhook URL, POST JSON to it.
6. If `webhook_secret` exists, sign the raw JSON payload with HMAC-SHA256 in `X-Billing-Signature`.
7. Store delivery status and delivery error on the event.

Webhook payload:

```json
{
  "event_id": "uuid",
  "rule": "Ops Health",
  "severity": "warning",
  "fingerprint": "task:bank_sync_payments:warning",
  "title": "Scheduled task bank_sync_payments is warning",
  "message": "No successful run in the last 3 minutes.",
  "context": {"task": "bank_sync_payments", "status": "warning"},
  "occurred_at": "2026-05-24T09:00:00+07:00"
}
```

Failures to deliver do not fail evaluation globally. The event remains `open` with `delivery_status = failed`.

## Scheduler Integration

Add the command to `ScheduledTaskRegistry` as `ops_alerts_evaluate => ops-alerts:evaluate`. Add a Laravel schedule entry every minute with `withoutOverlapping()` and name `ops_alerts_evaluate`. S14 scheduled task logging will record each alert evaluation run.

## Testing

Feature tests cover:

- Admin can create/update an ops health webhook rule without exposing stored secrets.
- `ops-alerts:evaluate` creates alert events for stale scheduled tasks and failed queues.
- Cooldown prevents duplicate event spam and updates `last_seen_at`.
- Webhook delivery posts signed JSON and records `delivered`.
- Webhook failure records `failed` without crashing evaluation.
- Admin can acknowledge and resolve events.
- Customer users cannot access alert routes.
- Scheduler configuration includes `scheduled-tasks:run ops_alerts_evaluate`.

## Deployment

The sprint is delivered through a PR to `develop`, CI, merge, and deploy to `/opt/billing`. After deploy, run migrations, seed, run `php artisan scheduled-tasks:run ops_alerts_evaluate`, and verify `/admin/ops-alert-events` redirects unauthenticated users to login.
