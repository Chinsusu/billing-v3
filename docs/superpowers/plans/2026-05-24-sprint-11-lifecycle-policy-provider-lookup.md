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

- [ ] Add admin product test storing `lifecycle_source=provider_lookup`, calendar-month fields, lookup path, JSON paths, date format, and timezone.
- [ ] Add checkout test proving product lifecycle policy is snapshotted into `services.meta.lifecycle_policy` and provisioning job payload.
- [ ] Add checkout test proving `calendar_month` uses no-overflow expiry.
- [ ] Add renewal test proving calendar-month renewal uses no-overflow expiry from the service snapshot.
- [ ] Run targeted Laravel tests on `/opt/billing` and verify failures are missing columns/fields/date service.
- [ ] Commit Laravel lifecycle RED tests.

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

- [ ] Add lifecycle columns with backward-compatible defaults.
- [ ] Add model fillable/casts/default factory values.
- [ ] Validate lifecycle fields and provider lookup fields in product requests.
- [ ] Save lifecycle fields from admin product forms.
- [ ] Add `ServiceLifecyclePolicy` to snapshot and calculate expiries.
- [ ] Use lifecycle snapshot in checkout and renewal.
- [ ] Run lifecycle tests until green.
- [ ] Commit Laravel lifecycle policy slice.

### Task 4: Laravel RED Tests For Provider Lifecycle Lookup

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningInternalExecutorTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ProvisioningExecutionAuditTest.php`

- [ ] Add generic HTTP executor test where provision succeeds, then lifecycle lookup returns `ordered_at` and `expires_at`.
- [ ] Assert executor JSON includes parsed ISO `ordered_at` and `expires_at`.
- [ ] Assert two HTTP calls are sent: provision path then lookup path with `{external_id}` substituted.
- [ ] Assert lookup creates a second `provisioning_execution_logs` row with action `provider_lifecycle_lookup`.
- [ ] Add lookup failure test returning 500 and assert executor returns 422 with `provider_lifecycle_http_error` logged.
- [ ] Run targeted Laravel tests and verify failures are missing lookup implementation/result fields.
- [ ] Commit provider lookup RED tests.

### Task 5: Laravel Provider Lookup Implementation

**Files:**
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderLifecycleDateParser.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProvisioningResult.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/GenericHttpProvisioningDriver.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/Drivers/SandboxProvisioningDriver.php`

- [ ] Add nullable `orderedAt` and `expiresAt` to `ProvisioningResult::toArray()`.
- [ ] Add date parser supporting `iso8601`, `unix_seconds`, and `unix_ms`.
- [ ] Add provider response date extraction for `lifecycle_source=provider_response`.
- [ ] Add provider lookup GET request for `lifecycle_source=provider_lookup`.
- [ ] Audit lookup success/failure with `ProvisioningExecutionRecorder`.
- [ ] Fail lookup with normalized lifecycle error codes.
- [ ] Run provider lookup tests until green.
- [ ] Commit Laravel provider lookup slice.

### Task 6: Go Worker Date Persistence

**Files:**
- Modify: `apps/worker-go/internal/provisioning/job.go`
- Modify: `apps/worker-go/internal/provisioning/processor.go`
- Modify: `apps/worker-go/internal/provisioning/processor_test.go`
- Modify: `apps/worker-go/internal/provisioningstore/store.go`
- Modify: `apps/worker-go/internal/provisioningstore/store_test.go`

- [ ] Add RED processor test decoding `ordered_at` and `expires_at`.
- [ ] Add RED store test updating `services.provisioned_at` and `services.expires_at`.
- [ ] Add nullable date fields to `provisioning.Result`.
- [ ] Parse RFC3339 dates from executor response.
- [ ] Update `MarkProcessed` SQL to set `provisioned_at` from result or `now()`, and only overwrite `expires_at` when result has one.
- [ ] Run `go fmt ./...`, `go vet ./...`, and `go test ./...` until green.
- [ ] Commit Go date persistence slice.

### Task 7: Runtime Smoke And Full Verification

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-11-lifecycle-policy-provider-lookup.md`

- [ ] Push branch to GitHub and checkout it on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run `php artisan runtime:smoke-provisioning --timeout=30`.
- [ ] Run Laravel Pint and full tests on `/opt/billing`.
- [ ] Run Go `go fmt ./...`, `go vet ./...`, and `go test ./...` on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Mark completed checklist items and commit plan progress.

### Task 8: PR, CI, Merge, Deploy

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-11-lifecycle-policy-provider-lookup.md`

- [ ] Open PR to `develop`.
- [ ] Wait for CI to pass.
- [ ] Merge PR and delete feature branch.
- [ ] Pull `develop` on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run S11 smoke command on `/opt/billing`.
- [ ] Verify `/products`, admin product form, and `billing_v3_worker`.
- [ ] Mark the plan complete on `develop`, commit, push, wait for CI, and update `/opt/billing`.
