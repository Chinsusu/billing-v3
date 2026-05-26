# Sprint 27 Admin Audit Log Design

## Goal

Add an admin-facing audit trail for sensitive operational changes. The audit log records who changed what, when, from which route/IP, and the before/after values that matter, while never storing raw secrets.

## Scope

Audit these admin mutations:

- Bank integration create/update.
- Provisioning provider account create/update.
- Product create/update/archive.
- Notification template update.
- Ops alert rule create/update.
- Customer wallet adjustment.
- Service refund credit.
- Service provider sync/cancel queue actions.
- Provider action job retry.

Customer self-service actions are out of scope. Automatic scheduler/worker state changes are out of scope unless an admin explicitly queues or retries an action.

## Data Model

Add `admin_audit_logs`:

- `id` UUID primary key.
- `actor_id` nullable foreign key to users with `nullOnDelete`.
- `actor_email` string snapshot.
- `action` string such as `created`, `updated`, `archived`, `wallet_adjusted`, `provider_sync_queued`.
- `auditable_type` model class or stable subject type.
- `auditable_id` string UUID/id snapshot.
- `auditable_label` human-readable subject label.
- `route_name`, `ip_address`, `user_agent`.
- `before`, `after`, `metadata` JSON objects.
- `created_at`.

The log is append-only. There is no update or delete UI in this sprint.

## Redaction

`AuditLogger` owns sanitization. It redacts any key containing secret-like names such as `api_key`, `webhook_secret`, `callback_secret`, `password`, `token`, `authorization`, or `private_key`. Last-four helper fields are safe to store. Nested arrays are sanitized recursively.

For update logs, blank secret inputs that intentionally preserve an existing secret should not create a false secret-change record. When a new secret is set, the audit row records only `[redacted]` plus any existing last-four field already stored by the domain model.

## UI and Permissions

Add `audit_logs.view`. `super_admin` receives it. Other roles do not receive it by default.

Routes:

- `GET /admin/audit-logs`
- `GET /admin/audit-logs/{adminAuditLog}`

Index filters:

- actor email contains.
- action exact.
- auditable type contains.
- auditable id exact.

Admin dashboard links to Audit Logs for users with `audit_logs.view`.

## Testing

Feature tests cover:

1. Bank integration create/update logs actor, route, before/after values, and redacts raw API/webhook secrets.
2. Provider account create/update logs config changes and redacts raw provider/callback secrets.
3. Product/template/manual operational actions create audit rows with useful metadata.
4. Audit UI filters rows, shows detail JSON, and blocks customers.

Verification:

- Laravel targeted audit tests.
- Existing bank/provider/product/notification/admin operation tests.
- Full `php artisan test`.
- Pint.
- Existing Go and compose CI checks.
