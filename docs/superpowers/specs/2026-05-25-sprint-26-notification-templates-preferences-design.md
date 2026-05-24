# Sprint 26 Notification Templates and Preferences Design

## Goal

Make customer email notifications configurable without weakening the durable outbox added in Sprint 22.

Sprint 26 adds admin-managed email templates and customer-managed per-type delivery preferences. Existing notification trigger code continues to call `NotificationOutbox::enqueue(...)`; the outbox renders a template when one exists and skips user-scoped notifications when the recipient has opted out.

## Non-Goals

- No third-party email provider integration.
- No HTML email editor or WYSIWYG builder.
- No in-app notification center.
- No backfill when a user disables notifications and later enables them again.
- No preference controls for operator notifications, because those have no user scope and are operational alerts.

## Data Model

Add `notification_templates`:

- `id` UUID primary key.
- `type` unique notification type, for example `invoice_paid`.
- `channel` string, initially `email`.
- `name` display name.
- `subject_template` text.
- `body_template` text.
- `variables` JSON array of supported variable names.
- `enabled` boolean. When disabled, the outbox falls back to the trigger-provided subject/body instead of blocking delivery.
- timestamps.

Add `notification_preferences`:

- `id` UUID primary key.
- `user_id` foreign key cascade delete.
- `type` notification type.
- `channel` string, initially `email`.
- `enabled` boolean.
- timestamps.
- unique index on `user_id`, `type`, `channel`.

Absence of a preference row means enabled. This keeps existing customers subscribed after migration.

## Template Catalog

Add a small Laravel service catalog that defines supported customer templates:

- `wallet_credited`
- `invoice_paid`
- `service_provisioned`
- `service_renewed`
- `service_cancellation_requested`
- `service_cancellation_completed`
- `service_expiry_warning`

Operator-only types such as `provider_action_failed` and `provider_callback_unmatched` are not exposed in customer preferences, but admins may still see/create templates for all existing types later. S26 seeds the customer-facing defaults above.

Template variables are simple scalar values drawn from notification payload and source metadata. The renderer supports `{{variable}}` placeholders and dot notation like `{{service.name}}` if nested payload data appears later. Unknown placeholders render as an empty string.

## Outbox Behavior

`NotificationOutbox::enqueue(...)` changes in two ways:

1. If `$user` is present and a preference row for `(user_id, type, email)` is disabled, return `null` and do not insert a `notification_events` row.
2. Otherwise, resolve an enabled template for `(type, email)`, render subject/body with merged variables, and store the rendered text in `notification_events`.

The method return type becomes `?NotificationEvent`. Call sites do not rely on the returned value today. Existing idempotency behavior remains unchanged for rows that are inserted.

Rendering variables use:

- the provided payload array,
- explicit fallback values: `type`, `recipient_email`, `source_type`, `source_id`,
- `user.name` and `user.email` when a user is present.

Existing trigger-provided subject/body remain the fallback if no enabled template exists or a template is intentionally disabled.

## Admin UX

Add admin routes under `notifications.manage`:

- `GET /admin/notification-templates`
- `GET /admin/notification-templates/{notificationTemplate}/edit`
- `PUT /admin/notification-templates/{notificationTemplate}`

The list shows type, name, enabled state, and variables. The edit form lets admins update name, enabled state, subject template, and body template. Variables are displayed as read-only guidance to reduce accidental mismatch.

The admin dashboard links to Notification Templates next to Notification Events.

## Customer UX

Add customer routes:

- `GET /notification-preferences`
- `POST /notification-preferences`

The preference page lists supported customer notification types with checkboxes. Posting the form upserts one preference row per known type for the authenticated user. Checked means enabled, unchecked means disabled.

The main layout adds a `Notifications` link for authenticated users.

## Error Handling

- Invalid template edits return Laravel validation errors.
- Unknown template placeholders do not throw; they render empty to keep notification enqueue resilient.
- Preference updates only accept supported customer types from the catalog and ignore unknown submitted keys through validation.
- Disabled preferences skip insertion silently; this is user intent, not an outbox failure.

## Testing

Feature tests cover:

1. Admin can view and edit a notification template, and the outbox uses the edited subject/body.
2. Customer can disable and re-enable a notification type.
3. Disabled customer preference prevents a matching `notification_events` row.
4. Preferences do not suppress operator notifications.
5. Seeders create default templates idempotently.
6. Routes are protected: customer cannot edit templates, guest cannot edit preferences.

Verification uses the existing Docker test server:

- Laravel Pint.
- Full Laravel test suite.
- Go `gofmt`, `go vet`, and `go test ./...` to ensure the monorepo stays green.
- Compose config/build.
- Secret scan.
- HTTPS smoke for `/notification-preferences`, `/admin/notification-templates`, `/admin/notification-events`, `/admin/ops-health`, and `/up`.
