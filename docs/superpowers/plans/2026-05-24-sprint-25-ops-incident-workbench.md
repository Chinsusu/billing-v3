# Sprint 25 Ops Incident Workbench Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add admin ops incident drill-down pages for alert events and scheduled task runs.

**Architecture:** Reuse existing `ops_alert_events` and `scheduled_task_runs` tables. Add read-only admin controllers/views plus links into existing acknowledge/resolve actions. No schema or scheduler behavior changes.

**Tech Stack:** Laravel 13, Eloquent, Blade, PHPUnit feature tests.

---

### Task 1: Commit Plan

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-25-ops-incident-workbench-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-25-ops-incident-workbench.md`

- [x] Save S25 design spec and implementation plan.
- [x] Commit docs before production code changes.

### Task 2: RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/OpsIncidentWorkbenchTest.php`

- [x] Add ops alert event detail test.
- [x] Add ops alert index link test.
- [x] Add scheduled task run list/filter test.
- [x] Add scheduled task run detail test.
- [x] Add ops health scheduled task links test.
- [x] Add customer forbidden test.
- [x] Run targeted test on `/opt/billing` and verify RED.
- [x] Commit RED tests.

### Task 3: Ops Alert Event Detail

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/OpsAlertEventController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/ops-alert-events/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/ops-alert-events/show.blade.php`

- [ ] Add `show` action loading rule and actor relationships.
- [ ] Add detail route protected by `provisioning_jobs.view`.
- [ ] Link event title/reference from index to detail.
- [ ] Render event metadata, context JSON, delivery state, and existing action forms.
- [ ] Run targeted test until alert detail checks are GREEN.
- [ ] Commit alert event detail slice.

### Task 4: Scheduled Task Run Workbench

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ScheduledTaskRunController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/ops-health/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/scheduled-task-runs/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/scheduled-task-runs/show.blade.php`

- [ ] Add list action with optional `task` and `status` filters.
- [ ] Add show action for full output/error snippets.
- [ ] Add routes protected by `provisioning_jobs.view`.
- [ ] Link ops health task rows to filtered runs and latest run detail.
- [ ] Add admin dashboard link.
- [ ] Run targeted test until all workbench checks are GREEN.
- [ ] Commit scheduled task run workbench slice.

### Task 5: Documentation, Verification, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-25-ops-incident-workbench.md`

- [ ] Update README with S25 routes.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go checks on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Verify `/admin/scheduled-task-runs`, `/admin/ops-alert-events/{missing}`, and `/up` smoke behavior.
- [ ] Mark verification steps complete in this plan, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch, and deploy `/opt/billing`.
