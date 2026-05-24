# Sprint 7 Worker Daemon Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the provisioning worker deployable as a daemon with retry backoff and stuck-job recovery.

**Architecture:** Go owns runtime execution, retry, and recovery through small store and daemon units. Laravel remains the admin control plane and only gains clearer provisioning job visibility. Docker Compose wires a dev worker service without changing CI checks.

**Tech Stack:** Go 1.26, `database/sql`, `github.com/lib/pq`, Laravel 13, Blade, Docker Compose.

---

### Task 1: S7 Documentation

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-7-worker-daemon-recovery-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-7-worker-daemon-recovery.md`

- [x] Save the approved S7 design.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders and contradictions.
- [x] Commit documentation before code.

### Task 2: Go RED Tests

**Files:**
- Modify: `apps/worker-go/internal/config/config_test.go`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`
- Create: `apps/worker-go/internal/provisioning/daemon_test.go`

- [x] Add config tests for `WORKER_POLL_INTERVAL`, `PROVISIONING_STUCK_AFTER`, `PROVISIONING_MAX_ATTEMPTS`, and `PROVISIONING_RETRY_BACKOFF`.
- [x] Add store tests for retry backoff and final failure in `MarkFailed`.
- [x] Add store tests for stuck job recovery to pending and failed.
- [x] Add daemon loop tests for repeated processing and sleeping when idle.
- [x] Run Go tests on the dev server and verify they fail because config fields, recovery methods, and daemon loop do not exist.

### Task 3: Worker Config And Store Implementation

**Files:**
- Modify: `apps/worker-go/internal/config/config.go`
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Modify: `apps/worker-go/internal/provisioningstore/store.go`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`

- [x] Add runtime config fields and duration parsing.
- [x] Add `Attempts` to provisioning jobs.
- [x] Add `RetryPolicy` and `NewStoreWithPolicy`.
- [x] Keep `NewStore` compatible with existing tests by using max attempts `1`.
- [x] Update `ClaimNext` to return attempt count after claim.
- [x] Update `MarkFailed` to either requeue with backoff or fail permanently.
- [x] Add `RecoverStuck` to move old `processing` jobs to `pending` or `failed`.
- [x] Run Go store/config tests and fix failures.

### Task 4: Worker Daemon Loop

**Files:**
- Create: `apps/worker-go/internal/provisioning/daemon.go`
- Modify: `apps/worker-go/cmd/worker/main.go`

- [x] Add a testable daemon runner that accepts an executor, recovery callback, poll interval, and sleep function.
- [x] Add `--daemon` flag while preserving `--once` behavior by default.
- [x] In daemon mode, recover stuck jobs before processing each job.
- [x] Use OS signal cancellation for long-running mode.
- [x] Run Go daemon tests and `go test ./...`.

### Task 5: Docker And Admin Visibility

**Files:**
- Modify: `infra/docker-compose.dev.yml`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-jobs/index.blade.php`
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningOperationsTest.php`
- Modify: `README.md`

- [x] Add Compose `worker` service using `golang:1.26.3` and `go run ./cmd/worker --daemon`.
- [x] Add admin table columns for `available_at`, `processed_at`, and `last_error`.
- [x] Add Laravel feature coverage that admin can see backoff/recovery timestamps and error text.
- [x] Document S7 worker daemon and recovery environment variables.
- [x] Run Laravel targeted tests and Docker compose config.

### Task 6: Final Verification And Publish

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-7-worker-daemon-recovery.md`

- [ ] Mark plan checklist complete after verification.
- [x] Run Laravel Pint and full tests on the dev server.
- [x] Run Go `gofmt`, `go vet`, and `go test`.
- [x] Run Docker compose config and secret scan.
- [ ] Commit and push branch.
- [ ] Open PR to `develop`, wait for CI, merge, and update `/opt/billing`.
