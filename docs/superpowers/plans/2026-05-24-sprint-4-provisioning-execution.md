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

- [x] Save the approved S4 design.
- [x] Save this implementation plan.
- [x] Scan both files for placeholders and contradictions.
- [x] Commit documentation before code.

### Task 2: Laravel RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProvisioningOperationsTest.php`

- [x] Add failing tests for customer service detail page showing external ID and provisioning history.
- [x] Add failing tests for customer being unable to view another user's service detail.
- [x] Add failing tests for admin retrying a failed provisioning job.
- [x] Add failing tests that processed provisioning jobs cannot be retried.
- [x] Run the new Laravel test file on the dev server and verify it fails on missing routes/controllers.

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

- [x] Implement owner-scoped service detail.
- [x] Implement retry service for failed jobs only.
- [x] Add retry POST route under admin provisioning jobs.
- [x] Add retry buttons and service links in Blade views.
- [x] Run Laravel S4 tests and verify they pass.

### Task 4: Go Worker RED Tests

**Files:**
- Modify: `apps/worker-go/go.mod`
- Create: `apps/worker-go/internal/provisioningstore/store_test.go`
- Create: `apps/worker-go/internal/provisioning/executor_test.go`

- [x] Add `sqlmock` and `lib/pq` dependencies.
- [x] Add failing tests for claiming a pending job.
- [x] Add failing tests for successful processing updating service/job records.
- [x] Add failing tests for failed processing marking job failed with `last_error`.
- [x] Run Go tests and verify they fail because store/executor do not exist.

### Task 5: Go Worker Implementation

**Files:**
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Create: `apps/worker-go/internal/provisioning/executor.go`
- Create: `apps/worker-go/internal/provisioningstore/store.go`
- Modify: `apps/worker-go/cmd/worker/main.go`

- [x] Implement provisioning job payload parsing and executor orchestration.
- [x] Implement SQL store claim/update methods.
- [x] Wire `cmd/worker` to open `DATABASE_URL` with Postgres driver and process one job.
- [x] Keep no-job behavior successful and quiet for dev usage.
- [x] Run Go tests and verify they pass.

### Task 6: Final Verification And Publish

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-4-provisioning-execution.md`

- [x] Mark plan checklist complete.
- [x] Run all Laravel tests.
- [x] Run Pint.
- [x] Run Go `gofmt`, `go vet`, and `go test`.
- [x] Run Docker compose config and secret scan.
- [ ] Commit, push, open PR to `develop`, wait for CI, merge, migrate/update `/opt/billing`.
