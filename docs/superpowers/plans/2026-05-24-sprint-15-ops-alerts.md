# Sprint 15 Ops Alerts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add proactive Ops Health alerting with persisted alert events, webhook delivery, cooldowns, and admin acknowledge/resolve controls.

**Architecture:** Laravel evaluates the existing `OpsHealthSnapshot` and creates `ops_alert_events` through enabled `ops_alert_rules`. Webhook delivery is synchronous in the evaluator command for this sprint, with failures recorded on the event instead of failing the entire evaluation. Admin UI follows the existing Blade/controller/RBAC patterns.

**Tech Stack:** Laravel 13 console commands, Eloquent/PostgreSQL migrations, Laravel HTTP client fake, Blade admin views, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/plans/2026-05-24-sprint-15-ops-alerts.md`

- [x] Save this S15 implementation plan.
- [x] Scan the plan for placeholders, contradictions, vague steps, and missing spec requirements.
- [ ] Commit the plan before production code changes.

Run:

```bash
git add docs/superpowers/plans/2026-05-24-sprint-15-ops-alerts.md
git commit -m "docs: add sprint 15 ops alerts plan"
```

### Task 2: RED Tests For Ops Alerts Core

**Files:**
- Create: `apps/backend-laravel/tests/Feature/OpsAlertsTest.php`

- [ ] Add feature tests for alert rules, alert events, evaluator behavior, webhook delivery, admin actions, and scheduler registration.

Test cases:

- `test_admin_can_create_update_and_view_ops_alert_rule_without_exposing_secret`
- `test_ops_alerts_evaluate_creates_task_and_queue_alert_events`
- `test_ops_alerts_evaluate_respects_cooldown_and_updates_last_seen`
- `test_ops_alerts_evaluate_posts_signed_webhook_payload`
- `test_ops_alerts_evaluate_records_webhook_failure_without_crashing`
- `test_admin_can_acknowledge_and_resolve_alert_event`
- `test_customer_cannot_access_ops_alert_pages`
- `test_scheduler_registers_ops_alert_evaluation_task`

- [ ] Run the targeted test on `/opt/billing` and verify RED.

Run:

```bash
ssh --% root@10.1.1.124 "cd /opt/billing && git fetch origin feature/sprint-15-ops-alerts && git reset --hard origin/feature/sprint-15-ops-alerts && docker compose -f infra/docker-compose.dev.yml exec -T backend sh -lc 'APP_ENV=testing php artisan test --filter=OpsAlertsTest'"
```

Expected: FAIL because `OpsAlertRule`, `OpsAlertEvent`, admin alert routes, and `ops-alerts:evaluate` do not exist.

- [ ] Commit RED tests.

Run:

```bash
git add apps/backend-laravel/tests/Feature/OpsAlertsTest.php
git commit -m "test: cover ops alerts"
git push origin feature/sprint-15-ops-alerts
```

### Task 3: Implement Ops Alert Schema And Models

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_140000_create_ops_alert_tables.php`
- Create: `apps/backend-laravel/app/Models/OpsAlertRule.php`
- Create: `apps/backend-laravel/app/Models/OpsAlertEvent.php`

- [ ] Add `ops_alert_rules` with encrypted `webhook_url` and `webhook_secret`.
- [ ] Add `ops_alert_events` with fingerprint, status lifecycle, context JSON, actor references, and delivery fields.
- [ ] Add Eloquent models with UUIDs, fillable attributes, casts, and relationships.
- [ ] Run targeted tests and verify remaining failures move to missing evaluator/routes/command.
- [ ] Commit schema/model slice.

### Task 4: Implement Evaluator, Webhook Delivery, Command, And Scheduler

**Files:**
- Create: `apps/backend-laravel/app/Services/Ops/OpsAlertCandidate.php`
- Create: `apps/backend-laravel/app/Services/Ops/OpsAlertEvaluator.php`
- Create: `apps/backend-laravel/app/Console/Commands/EvaluateOpsAlertsCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/app/Services/Scheduler/ScheduledTaskRegistry.php`
- Modify: `apps/backend-laravel/routes/console.php`

- [ ] Evaluate enabled `ops_health` rules from `OpsHealthSnapshot`.
- [ ] Emit task, queue, overdue service, and missing bank integration alert candidates.
- [ ] Enforce unresolved event cooldown by fingerprint and rule.
- [ ] Deliver webhook payloads with optional HMAC signature.
- [ ] Record `delivered`, `failed`, or `skipped` delivery status.
- [ ] Add `ops-alerts:evaluate` command.
- [ ] Add scheduled task registry key `ops_alerts_evaluate`.
- [ ] Add Laravel scheduler entry `scheduled-tasks:run ops_alerts_evaluate` every minute, without overlapping, named `ops_alerts_evaluate`.
- [ ] Run evaluator and scheduler tests until only admin UI tests fail.
- [ ] Commit evaluator slice.

### Task 5: Implement Admin Ops Alert UI

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertRuleController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertEventController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertEventAcknowledgeController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertEventResolveController.php`
- Create: `apps/backend-laravel/resources/views/admin/ops-alert-rules/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/ops-alert-events/index.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add alert rule list/create/update in one compact admin page.
- [ ] Preserve existing webhook secret when update secret input is blank.
- [ ] Never render webhook URL or secret values back to the browser.
- [ ] Add alert event list with status, severity, title, message, timestamps, and ack/resolve forms.
- [ ] Add admin routes guarded by `permission:provisioning_jobs.view`.
- [ ] Add dashboard link to `/admin/ops-alert-events`.
- [ ] Run `OpsAlertsTest` until GREEN.
- [ ] Commit admin UI slice.

### Task 6: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-15-ops-alerts.md`

- [ ] Update README with S15 routes and command.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Recreate backend/scheduler and run migrations.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Run smoke: `php artisan scheduled-tasks:run ops_alerts_evaluate`.
- [ ] Verify `/admin/ops-alert-events` unauthenticated returns `302`.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
