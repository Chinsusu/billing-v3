# Sprint 22 Notification Outbox Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a durable email notification outbox with retryable delivery and admin inspection.

**Architecture:** Laravel owns the notification outbox table, enqueue service, send command, scheduler registration, and admin UI. Existing Laravel lifecycle flows enqueue idempotent notification rows at their mutation points. The Go provisioning worker inserts the `service_provisioned` outbox row in the same transaction that marks a service active.

**Tech Stack:** Laravel 13, Eloquent, Blade, Laravel Mail, PHPUnit feature tests, Go SQL worker tests.

---

### Task 1: Commit Spec and Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-22-notification-outbox-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-22-notification-outbox.md`

- [x] Save S22 design spec.
- [x] Save S22 implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/NotificationOutboxTest.php`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`

- [x] Add Laravel tests for wallet credit, invoice paid, sending, failure/retry, admin UI/retry, expiry warning, provider callback unmatched, provider action final failure, and scheduler registration.
- [x] Add Go test expectation that `MarkProcessed` inserts a `service_provisioned` outbox row.
- [x] Copy tests to `/opt/billing` and verify RED failures.
- [x] Commit RED tests.

### Task 3: Outbox Schema, Model, Enqueue Service

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_170000_create_notification_events_table.php`
- Create: `apps/backend-laravel/app/Models/NotificationEvent.php`
- Create: `apps/backend-laravel/app/Services/Notifications/NotificationOutbox.php`

- [x] Add notification_events table with unique idempotency key and delivery indexes.
- [x] Add NotificationEvent model casts and relationships.
- [x] Add idempotent NotificationOutbox enqueue method.
- [x] Run targeted Laravel tests until schema/service assertions pass.
- [x] Commit schema/service slice.

### Task 4: Delivery Command, Scheduler, Ops Health

**Files:**
- Create: `apps/backend-laravel/app/Console/Commands/SendNotificationsCommand.php`
- Create: `apps/backend-laravel/app/Services/Notifications/NotificationDispatcher.php`
- Create: `apps/backend-laravel/app/Services/Notifications/ServiceExpiryWarningNotifier.php`
- Modify: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Modify: `apps/backend-laravel/app/Services/Ops/OpsHealthSnapshot.php`
- Modify: `apps/backend-laravel/routes/console.php`
- Modify: `apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php`

- [x] Claim pending notifications with row locks.
- [x] Send email via Laravel Mail raw text.
- [x] Retry transient failures and mark exhausted attempts failed.
- [x] Queue due service expiry warnings once.
- [x] Register `notifications_send` scheduled task and ops health freshness.
- [x] Run targeted Laravel tests until delivery/scheduler assertions pass.
- [x] Commit delivery/scheduler slice.

### Task 5: Lifecycle Trigger Integration

**Files:**
- Modify: `apps/backend-laravel/app/Services/Finance/BankWebhookProcessor.php`
- Modify: `apps/backend-laravel/app/Services/Finance/PaymentEventReconciliationService.php`
- Modify: `apps/backend-laravel/app/Services/Finance/InvoicePaymentService.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceRenewalService.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceCancellationController.php`
- Modify: `apps/backend-laravel/app/Services/Services/ScheduledServiceCancellationProcessor.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProviderActionJobProcessor.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProviderCallbackProcessor.php`
- Modify: `apps/worker-go/internal/provisioningstore/store.go`

- [x] Enqueue wallet credit and invoice paid notifications.
- [x] Enqueue service renewal and cancellation requested/completed notifications.
- [x] Enqueue provider callback unmatched and provider action final failure notifications.
- [x] Insert service provisioned notifications from Go worker.
- [x] Run targeted Laravel and Go tests until lifecycle assertions pass.
- [x] Commit trigger integration slice.

### Task 6: Admin Notification Workbench

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/NotificationEventController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/NotificationEventRetryController.php`
- Create: `apps/backend-laravel/resources/views/admin/notification-events/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/notification-events/show.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`

- [x] Add `notifications.manage` permission for super admin and ops admin.
- [x] Add index/detail/retry admin routes.
- [x] Render filters, delivery state, payload, body, and retry form.
- [x] Run targeted admin UI tests until GREEN.
- [x] Commit admin workbench slice.

### Task 7: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-22-notification-outbox.md`

- [x] Update README with S22 notification routes/command.
- [ ] Push branch and check it out on `/opt/billing`.
- [x] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [x] Run Go checks on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [ ] Verify `/admin/notification-events`, `/admin/ops-health`, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
