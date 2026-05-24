# Sprint 10 Provider Execution Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add provider execution audit logs, provider account testing, and provider config persistence for provisioned services.

**Architecture:** Laravel records redacted provider execution logs around sandbox, generic HTTP, and admin provider test calls. Go continues to claim and finalize jobs but now persists the `config` returned by Laravel into `services.config`.

**Tech Stack:** Laravel 13, Blade admin views, Laravel HTTP client, encrypted casts, Go 1.26, Postgres JSON.

---

### Task 1: Documentation Checkpoint

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-10-provider-execution-audit-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-10-provider-execution-audit.md`

- [x] Save the approved S10 design spec.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders, contradictions, and vague scope.
- [x] Commit spec and plan before code changes.

### Task 2: Laravel RED Tests For Audit Logs And Provider Account Testing

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProvisioningExecutionAuditTest.php`
- Create: `apps/backend-laravel/tests/Feature/ProvisioningProviderAccountTestHarnessTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningOperationsTest.php`

- [ ] Add test: generic HTTP internal executor records successful redacted execution log.
- [ ] Add test: generic HTTP provider failure records failed execution log and returns `422`.
- [ ] Add test: admin can test generic provider account and update `last_test_*` fields.
- [ ] Add test: failed provider account test stores error without leaking API key.
- [ ] Add test: admin provisioning job detail page displays execution logs.
- [ ] Run targeted Laravel tests on `/opt/billing` and verify they fail because log table/routes/classes do not exist.
- [ ] Commit Laravel RED tests.

### Task 3: Laravel Audit Log Model, Migration, And Redaction

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_090000_create_provisioning_execution_logs_table.php`
- Create: `apps/backend-laravel/app/Models/ProvisioningExecutionLog.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/PayloadRedactor.php`
- Modify: `apps/backend-laravel/app/Models/ProvisioningJob.php`
- Modify: `apps/backend-laravel/app/Models/Service.php`
- Modify: `apps/backend-laravel/app/Models/ProvisioningProviderAccount.php`

- [ ] Add log table with nullable job/service/provider account foreign keys.
- [ ] Add model casts for request/response payload JSON.
- [ ] Add relationships from jobs, services, and provider accounts to logs.
- [ ] Add redactor tests through feature tests using secret-like payload fields.
- [ ] Run targeted Laravel audit tests and verify failures move to missing execution code.
- [ ] Commit audit model slice.

### Task 4: Laravel Executor Logging And Provider Account Test Harness

**Files:**
- Create: `apps/backend-laravel/app/Services/Provisioning/ProvisioningExecutionRecorder.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderAccountTester.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningProviderAccountTestController.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/SandboxProvisioningDriver.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/GenericHttpProvisioningDriver.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProvisioningExecutor.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/index.blade.php`

- [ ] Record success/failure logs for sandbox executor calls.
- [ ] Record success/failure logs for generic HTTP executor calls.
- [ ] Normalize errors to codes such as `provider_http_error`, `provider_invalid_json`, `provider_missing_external_id`, and `provider_unsupported_status`.
- [ ] Add provider account test service and controller.
- [ ] Add test button to provider account admin index.
- [ ] Run provider account test harness and executor audit tests until green.
- [ ] Commit executor logging and test harness slice.

### Task 5: Admin Provisioning Job Detail

**Files:**
- Create: `apps/backend-laravel/resources/views/admin/provisioning-jobs/show.blade.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningJobController.php`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-jobs/index.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`

- [ ] Add `show` action eager-loading service, user, and execution logs.
- [ ] Link job references on index to job detail.
- [ ] Render execution logs with redacted request/response JSON.
- [ ] Run admin operations test until green.
- [ ] Commit admin job detail slice.

### Task 6: Go Worker Config Persistence

**Files:**
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Modify: `apps/worker-go/internal/provisioning/processor_test.go`
- Modify: `apps/worker-go/internal/provisioning/processor.go`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`
- Modify: `apps/worker-go/internal/provisioningstore/store.go`

- [ ] Add RED Go test that internal executor processor decodes `config`.
- [ ] Add RED Go store test that `MarkProcessed` updates `services.config`.
- [ ] Add `Config map[string]any` to `provisioning.Result`.
- [ ] Marshal config in `MarkProcessed`, defaulting nil to `{}`.
- [ ] Run `go fmt ./...`, `go vet ./...`, and `go test ./...` until green.
- [ ] Commit Go config persistence slice.

### Task 7: Runtime Smoke And Full Verification

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-10-provider-execution-audit.md`

- [ ] Push branch to GitHub and checkout it on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run `php artisan runtime:smoke-provisioning --timeout=30`.
- [ ] Verify the smoke job has at least one `provisioning_execution_logs` row.
- [ ] Run Laravel Pint and full tests on `/opt/billing`.
- [ ] Run Go `go fmt ./...`, `go vet ./...`, and `go test ./...` on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Mark completed checklist items and commit plan progress.

### Task 8: PR, CI, Merge, Deploy

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-10-provider-execution-audit.md`

- [ ] Open PR to `develop`.
- [ ] Wait for CI to pass.
- [ ] Merge PR and delete feature branch.
- [ ] Pull `develop` on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run S10 smoke command on `/opt/billing`.
- [ ] Verify `/products`, `/admin/provisioning-jobs`, and `billing_v3_worker`.
- [ ] Mark the plan complete on `develop`, commit, push, wait for CI, and update `/opt/billing`.
