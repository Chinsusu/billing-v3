# Sprint 4 Provisioning Execution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute pending provisioning jobs through the Go worker sandbox provider and surface retry/status behavior in Laravel.

**Architecture:** Laravel remains the control plane for service/job visibility and retry. Go owns provisioning execution with a database-backed store, a pure sandbox processor, and a one-shot worker command. Tests stay TDD-first and CI-friendly using Laravel feature tests plus Go `sqlmock` unit tests.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests, Go 1.26, `database/sql`, `github.com/DATA-DOG/go-sqlmock`, `github.com/lib/pq`.

---

### Task 1: S4 Documentation

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-4-provisioning-execution-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-4-provisioning-execution.md`

- [ ] Save the approved S4 design.
- [ ] Save this implementation plan.
- [ ] Scan both files for placeholders and contradictions.
- [ ] Commit documentation before code.

### Task 2: Laravel RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProvisioningOperationsTest.php`

- [ ] Add failing tests for customer service detail page showing external ID and provisioning history.
- [ ] Add failing tests for customer being unable to view another user's service detail.
- [ ] Add failing tests for admin retrying a failed provisioning job.
- [ ] Add failing tests that processed provisioning jobs cannot be retried.
- [ ] Run the new Laravel test file on the dev server and verify it fails on missing routes/controllers.

### Task 3: Laravel Implementation

**Files:**
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningJobRetryController.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProvisioningJobRetryService.php`
- Create: `apps/backend-laravel/resources/views/services/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/services/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-jobs/index.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `README.md`

- [ ] Implement owner-scoped service detail.
- [ ] Implement retry service for failed jobs only.
- [ ] Add retry POST route under admin provisioning jobs.
- [ ] Add retry buttons and service links in Blade views.
- [ ] Run Laravel S4 tests and verify they pass.

### Task 4: Go Worker RED Tests

**Files:**
- Modify: `apps/worker-go/go.mod`
- Create: `apps/worker-go/internal/provisioningstore/store_test.go`
- Create: `apps/worker-go/internal/provisioning/executor_test.go`

- [ ] Add `sqlmock` and `lib/pq` dependencies.
- [ ] Add failing tests for claiming a pending job.
- [ ] Add failing tests for successful processing updating service/job records.
- [ ] Add failing tests for failed processing marking job failed with `last_error`.
- [ ] Run Go tests and verify they fail because store/executor do not exist.

### Task 5: Go Worker Implementation

**Files:**
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Create: `apps/worker-go/internal/provisioning/executor.go`
- Create: `apps/worker-go/internal/provisioningstore/store.go`
- Modify: `apps/worker-go/cmd/worker/main.go`

- [ ] Implement provisioning job payload parsing and executor orchestration.
- [ ] Implement SQL store claim/update methods.
- [ ] Wire `cmd/worker` to open `DATABASE_URL` with Postgres driver and process one job.
- [ ] Keep no-job behavior successful and quiet for dev usage.
- [ ] Run Go tests and verify they pass.

### Task 6: Final Verification And Publish

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-4-provisioning-execution.md`

- [ ] Mark plan checklist complete.
- [ ] Run all Laravel tests.
- [ ] Run Pint.
- [ ] Run Go `gofmt`, `go vet`, and `go test`.
- [ ] Run Docker compose config and secret scan.
- [ ] Commit, push, open PR to `develop`, wait for CI, merge, migrate/update `/opt/billing`.
