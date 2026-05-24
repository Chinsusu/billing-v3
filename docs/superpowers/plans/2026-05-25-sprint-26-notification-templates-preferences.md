# Sprint 26 Notification Templates and Preferences Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin-managed notification templates and customer-managed notification preferences.

**Architecture:** Laravel stores template definitions and user preferences in new tables. `NotificationOutbox` becomes the single enforcement point for template rendering and opt-out checks, so existing notification triggers do not need broad rewrites.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests, Docker Compose dev runtime.

---

### Task 1: Commit Design and Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-25-sprint-26-notification-templates-preferences-design.md`
- Create: `docs/superpowers/plans/2026-05-25-sprint-26-notification-templates-preferences.md`

- [ ] Save the S26 design spec.
- [ ] Save the S26 implementation plan.
- [ ] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/NotificationTemplatePreferenceTest.php`
- Modify: `apps/backend-laravel/tests/Feature/SchedulerConfigurationTest.php`

- [ ] Add a test that an admin can edit an `invoice_paid` template and a later outbox row uses the rendered subject/body.
- [ ] Add a test that a customer can disable `invoice_paid` from `/notification-preferences`, suppressing customer events.
- [ ] Add a test that re-enabling the same type allows customer events again.
- [ ] Add a test that operator notifications still enqueue when a customer has disabled customer notifications.
- [ ] Add a test that the template seeder is idempotent and default templates exist.
- [ ] Add route protection assertions for customer/admin boundaries.
- [ ] Copy tests to `/opt/billing` and verify RED failures caused by missing tables/routes/models.
- [ ] Commit RED tests.

### Task 3: Schema, Models, and Catalog

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_25_090000_create_notification_templates_table.php`
- Create: `apps/backend-laravel/database/migrations/2026_05_25_090100_create_notification_preferences_table.php`
- Create: `apps/backend-laravel/app/Models/NotificationTemplate.php`
- Create: `apps/backend-laravel/app/Models/NotificationPreference.php`
- Create: `apps/backend-laravel/app/Services/Notifications/NotificationTemplateCatalog.php`
- Create: `apps/backend-laravel/database/seeders/NotificationTemplateSeeder.php`
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`

- [ ] Add `notification_templates` migration with unique `(type, channel)`.
- [ ] Add `notification_preferences` migration with unique `(user_id, type, channel)`.
- [ ] Add Eloquent models with casts and relationships.
- [ ] Add a catalog with default templates and customer preference types.
- [ ] Seed default templates idempotently from the catalog.
- [ ] Run targeted seeder/model tests until schema assertions pass.
- [ ] Commit schema/catalog slice.

### Task 4: Template Rendering and Preference Enforcement

**Files:**
- Create: `apps/backend-laravel/app/Services/Notifications/NotificationTemplateRenderer.php`
- Create: `apps/backend-laravel/app/Services/Notifications/NotificationPreferenceService.php`
- Modify: `apps/backend-laravel/app/Services/Notifications/NotificationOutbox.php`

- [ ] Write renderer that replaces `{{variable}}` and `{{nested.value}}` placeholders.
- [ ] Write preference service with `enabled(User $user, string $type, string $channel): bool` and `sync(User $user, array $enabledTypes): void`.
- [ ] Update `NotificationOutbox::enqueue()` to return `?NotificationEvent`, skip disabled user events, and render enabled templates.
- [ ] Preserve fallback subject/body when no enabled template exists.
- [ ] Run targeted outbox tests until GREEN.
- [ ] Commit rendering/preference slice.

### Task 5: Admin Template Workbench

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/NotificationTemplateController.php`
- Create: `apps/backend-laravel/resources/views/admin/notification-templates/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/notification-templates/edit.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add index/edit/update actions guarded by `notifications.manage`.
- [ ] Render template variables as read-only helper text.
- [ ] Validate subject/body/name/enabled input on update.
- [ ] Add admin dashboard link.
- [ ] Run targeted admin template tests until GREEN.
- [ ] Commit admin workbench slice.

### Task 6: Customer Preference Page

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/NotificationPreferenceController.php`
- Create: `apps/backend-laravel/resources/views/notification-preferences/edit.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`

- [ ] Add preference edit/update actions for authenticated users.
- [ ] Render one checkbox per customer-facing notification type.
- [ ] Upsert preferences for all catalog customer types on submit.
- [ ] Add authenticated navigation link.
- [ ] Run targeted preference tests until GREEN.
- [ ] Commit customer preference slice.

### Task 7: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-26-notification-templates-preferences.md`

- [ ] Update README with S26 routes and behavior.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/notification-preferences`, `/admin/notification-templates`, `/admin/notification-events`, `/admin/ops-health`, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
