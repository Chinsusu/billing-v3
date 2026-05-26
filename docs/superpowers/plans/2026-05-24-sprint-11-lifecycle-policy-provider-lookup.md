# Sprint 11 Lifecycle Policy Provider Lookup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-product lifecycle policies and provider lifecycle lookup dates after provisioning.

**Architecture:** Laravel owns lifecycle policy configuration, local date calculation, provider lookup, and audit logging. The internal executor response grows nullable `ordered_at` and `expires_at` fields, and the Go worker persists those dates into `services.provisioned_at` and `services.expires_at`.

**Tech Stack:** Laravel 13, Blade admin forms, Laravel HTTP client, Carbon, Go 1.26, Postgres timestamps.

---

### Task 1: Documentation Checkpoint

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-11-lifecycle-policy-provider-lookup-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-11-lifecycle-policy-provider-lookup.md`

- [x] Save the approved S11 design spec.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders, contradictions, and vague scope.
- [x] Commit spec and plan before code changes.

### Task 2: Laravel RED Tests For Lifecycle Policy

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProductLifecyclePolicyTest.php`
- Modify: `apps/backend-laravel/tests/Feature/OrderCheckoutFlowTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ServiceRenewalLifecycleTest.php`

- [x] Add admin product test storing `lifecycle_source=provider_lookup`, calendar-month fields, lookup path, JSON paths, date format, and timezone.
- [x] Add checkout test proving product lifecycle policy is snapshotted into `services.meta.lifecycle_policy` and provisioning job payload.
- [x] Add checkout test proving `calendar_month` uses no-overflow expiry.
- [x] Add renewal test proving calendar-month renewal uses no-overflow expiry from the service snapshot.
- [x] Run targeted Laravel tests on `/opt/billing` and verify failures are missing columns/fields/date service.
- [x] Commit Laravel lifecycle RED tests.

### Task 3: Laravel Lifecycle Policy Model, Migration, And Calculator

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_100000_add_lifecycle_policy_to_products_table.php`
- Create: `apps/backend-laravel/app/Services/Services/ServiceLifecyclePolicy.php`
- Modify: `apps/backend-laravel/app/Models/Product.php`
- Modify: `apps/backend-laravel/database/factories/ProductFactory.php`
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`
- Modify: `apps/backend-laravel/app/Http/Requests/StoreProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Requests/UpdateProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Modify: `apps/backend-laravel/resources/views/admin/products/_form.blade.php`
- Modify: `apps/backend-laravel/app/Services/Orders/OrderCheckoutService.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceRenewalService.php`

- [x] Add lifecycle columns with backward-compatible defaults.
- [x] Add model fillable/casts/default factory values.
- [x] Validate lifecycle fields and provider lookup fields in product requests.
- [x] Save lifecycle fields from admin product forms.
- [x] Add `ServiceLifecyclePolicy` to snapshot and calculate expiries.
- [x] Use lifecycle snapshot in checkout and renewal.
- [x] Run lifecycle tests until green.
- [x] Commit Laravel lifecycle policy slice.

### Task 4: Laravel RED Tests For Provider Lifecycle Lookup

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningInternalExecutorTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningExecutionAuditTest.php`

- [x] Add generic HTTP executor test where provision succeeds, then lifecycle lookup returns `ordered_at` and `expires_at`.
- [x] Assert executor JSON includes parsed ISO `ordered_at` and `expires_at`.
- [x] Assert two HTTP calls are sent: provision path then lookup path with `{external_id}` substituted.
- [x] Assert lookup creates a second `provisioning_execution_logs` row with action `provider_lifecycle_lookup`.
- [x] Add lookup failure test returning 500 and assert executor returns 422 with `provider_lifecycle_http_error` logged.
- [x] Run targeted Laravel tests and verify failures are missing lookup implementation/result fields.
- [x] Commit provider lookup RED tests.

### Task 5: Laravel Provider Lookup Implementation

**Files:**
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderLifecycleDateParser.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProvisioningResult.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/GenericHttpProvisioningDriver.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/SandboxProvisioningDriver.php`

- [x] Add nullable `orderedAt` and `expiresAt` to `ProvisioningResult::toArray()`.
- [x] Add date parser supporting `iso8601`, `unix_seconds`, and `unix_ms`.
- [x] Add provider response date extraction for `lifecycle_source=provider_response`.
- [x] Add provider lookup GET request for `lifecycle_source=provider_lookup`.
- [x] Audit lookup success/failure with `ProvisioningExecutionRecorder`.
- [x] Fail lookup with normalized lifecycle error codes.
- [x] Run provider lookup tests until green.
- [x] Commit Laravel provider lookup slice.

### Task 6: Go Worker Date Persistence

**Files:**
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Modify: `apps/worker-go/internal/provisioning/processor.go`
- Modify: `apps/worker-go/internal/provisioning/processor_test.go`
- Modify: `apps/worker-go/internal/provisioningstore/store.go`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`

- [x] Add RED processor test decoding `ordered_at` and `expires_at`.
- [x] Add RED store test updating `services.provisioned_at` and `services.expires_at`.
- [x] Add nullable date fields to `provisioning.Result`.
- [x] Parse RFC3339 dates from executor response.
- [x] Update `MarkProcessed` SQL to set `provisioned_at` from result or `now()`, and only overwrite `expires_at` when result has one.
- [x] Run `go fmt ./...`, `go vet ./...`, and `go test ./...` until green.
- [x] Commit Go date persistence slice.

### Task 7: Runtime Smoke And Full Verification

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-11-lifecycle-policy-provider-lookup.md`

- [x] Push branch to GitHub and checkout it on `/opt/billing`.
- [x] Recreate Compose backend and worker.
- [x] Run `php artisan runtime:smoke-provisioning --timeout=30`.
- [x] Run Laravel Pint and full tests on `/opt/billing`.
- [x] Run Go `go fmt ./...`, `go vet ./...`, and `go test ./...` on `/opt/billing`.
- [x] Run Docker Compose config/build and secret scan.
- [x] Mark completed checklist items and commit plan progress.

### Task 8: PR, CI, Merge, Deploy

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-11-lifecycle-policy-provider-lookup.md`

- [x] Open PR to `develop`.
- [x] Wait for CI to pass.
- [x] Merge PR and delete feature branch.
- [x] Pull `develop` on `/opt/billing`.
- [x] Recreate Compose backend and worker.
- [x] Run S11 smoke command on `/opt/billing`.
- [x] Verify `/products`, admin product form, and `billing_v3_worker`.
- [x] Mark the plan complete on `develop`, commit, push, wait for CI, and update `/opt/billing`.
