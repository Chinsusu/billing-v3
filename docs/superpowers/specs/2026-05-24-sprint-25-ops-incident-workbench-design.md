# Sprint 25 Ops Incident Workbench Design

## Goal

Give admins a focused incident workbench for ops alerts and scheduled task runs so they can move from a health warning to the exact event/run context without reading database rows or container logs first.

## Scope

Sprint 25 adds:

- Ops alert event detail page: `GET /admin/ops-alert-events/{opsAlertEvent}`.
- Scheduled task run list page: `GET /admin/scheduled-task-runs`.
- Scheduled task run detail page: `GET /admin/scheduled-task-runs/{scheduledTaskRun}`.
- Links from `/admin/ops-alert-events` rows to event detail.
- Links from `/admin/ops-health` task rows to filtered scheduled task runs and latest run detail.
- Admin dashboard link to scheduled task runs.

## Non-Goals

This sprint does not add charts, time-series storage, alert rule builders beyond the existing rules page, arbitrary command execution from the browser, scheduler mutation, or new background jobs.

## Alert Event Detail

The detail page shows:

- Event status, severity, fingerprint, title, and message.
- Rule name/type/severity/cooldown when still available.
- First seen, last seen, acknowledged, and resolved timestamps.
- Acknowledged/resolved actor emails when available.
- Delivery status and delivery error.
- Pretty-printed context JSON.
- Existing acknowledge and resolve actions when applicable.

## Scheduled Task Runs

The list page shows latest runs first and supports optional `task` and `status` query filters.

Each row shows:

- Task key and command.
- Status, exit code, duration, started, and finished timestamps.
- A short output/error preview.
- Link to the run detail page.

The detail page shows all run metadata plus full stored output and error snippets.

## Permissions

All new pages reuse the existing admin ops permission surface:

- `permission:admin.access`
- `permission:provisioning_jobs.view`

Customers must remain forbidden from these pages.

## Testing

Feature tests cover:

- Admin can open an ops alert event detail page with rule, context, delivery, and action controls.
- Ops alert index links event rows to detail.
- Admin can list scheduled task runs and filter by task/status.
- Admin can view a scheduled task run detail with output and error.
- Ops health links scheduled task rows to run filters/latest run detail.
- Customer cannot access the new workbench pages.
