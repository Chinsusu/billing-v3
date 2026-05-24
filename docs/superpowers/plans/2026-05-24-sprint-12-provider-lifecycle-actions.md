# Sprint 12 Provider Lifecycle Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Connect service renew, expiry suspension, cancellation-ready actions, and admin sync to configurable provider HTTP endpoints.

**Architecture:** Laravel remains the provider control plane because it owns encrypted provider secrets and audit logging. Products store plan-specific action paths, checkout snapshots non-secret provider action config into services, and a focused provider action service executes authenticated HTTP calls with redacted execution logs. Go worker behavior is unchanged.

**Tech Stack:** Laravel 13, PostgreSQL migrations, Laravel HTTP client fakes, Blade admin views, PHPUnit/Pest-style Laravel feature tests, existing Go worker smoke verification.

---

### Task 1: Commit Plan And Prepare Branch

**Files:**
- Create: `docs/superpowers/plans/2026-05-24-sprint-12-provider-lifecycle-actions.md`

- [x] Save the S12 implementation plan.
- [x] Scan the plan for placeholders, contradictions, and vague scope.
- [x] Commit the plan before code changes.

### Task 2: RED Tests For Product Action Config

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ProductLifecyclePolicyTest.php`
- Modify: `apps/backend-laravel/tests/Feature/OrderCheckoutFlowTest.php`

- [ ] Add a product admin test that posts `provider_renew_path`, `provider_suspend_path`, `provider_cancel_path`, and `provider_sync_path`, then asserts they are stored.
- [ ] Add a checkout test proving `services.meta.provider` snapshots provider action paths.
- [ ] Run targeted Laravel tests on `/opt/billing` and verify failures are missing product columns/request fields/snapshot fields.
- [ ] Commit RED config tests.

### Task 3: Implement Product Action Config

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_110000_add_provider_action_paths_to_products_table.php`
- Modify: `apps/backend-laravel/app/Models/Product.php`
- Modify: `apps/backend-laravel/database/factories/ProductFactory.php`
- Modify: `apps/backend-laravel/app/Http/Requests/StoreProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Requests/UpdateProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Modify: `apps/backend-laravel/resources/views/admin/products/_form.blade.php`
- Modify: `apps/backend-laravel/app/Services/Orders/OrderCheckoutService.php`

- [ ] Add nullable product action path columns for renew, suspend, cancel, and sync.
- [ ] Add model fillable/factory defaults.
- [ ] Validate action paths as nullable strings that start with `/`.
- [ ] Save action paths from admin product forms.
- [ ] Include action paths in checkout service provider snapshots and provisioning job provider payloads.
- [ ] Run config tests until green.
- [ ] Commit product action config slice.

### Task 4: RED Tests For Provider Renewal

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ServiceRenewalLifecycleTest.php`

- [ ] Add test: customer renewal calls provider renew with auth, idempotency key, external id, and action payload.
- [ ] Assert provider-returned expiry overrides local calculated expiry.
- [ ] Assert wallet debit and renewal meta are written only after provider success.
- [ ] Assert `provisioning_execution_logs` records `provider_service_renew`.
- [ ] Add test: provider renewal failure leaves wallet balance, ledger, and service expiry unchanged.
- [ ] Run renewal tests on `/opt/billing` and verify failures are missing provider action implementation.
- [ ] Commit RED renewal tests.

### Task 5: Implement Provider Action Service And Renewal Integration

**Files:**
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderServiceActionResult.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProviderServiceActionService.php`
- Modify: `apps/backend-laravel/app/Services/Provisioning/ProvisioningExecutionRecorder.php`
- Modify: `apps/backend-laravel/app/Services/Services/ServiceRenewalService.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ServiceRenewalController.php`

- [ ] Add `ProviderServiceActionResult` with `status`, `expiresAt`, and `response`.
- [ ] Add recorder method for service-scoped execution logs.
- [ ] Add provider action service that resolves provider config, renders placeholders, sends authenticated HTTP, parses optional expiry, and records audit logs.
- [ ] Inject provider action service into renewal.
- [ ] For configured renew paths, call provider renew before debit and use provider expiry when returned.
- [ ] Convert provider action failures to session errors on service renew page.
- [ ] Run renewal tests until green.
- [ ] Commit provider renewal slice.

### Task 6: RED Tests For Expiry Suspension

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/ServiceRenewalLifecycleTest.php`

- [ ] Add test: `services:expire` calls provider suspend before marking overdue service expired.
- [ ] Assert suspend audit log action is `provider_service_suspend`.
- [ ] Add test: provider suspend failure leaves overdue service active and command exits non-zero.
- [ ] Run expiry tests on `/opt/billing` and verify failures are missing suspend implementation.
- [ ] Commit RED expiry suspend tests.

### Task 7: Implement Expiry Suspension

**Files:**
- Modify: `apps/backend-laravel/app/Console/Commands/ExpireServicesCommand.php`

- [ ] Inject provider action service into `services:expire`.
- [ ] Call provider suspend for overdue services with configured suspend path.
- [ ] Mark local expired only when provider suspend succeeds or no suspend path is configured.
- [ ] Count failures, print `Expired X services. Failed Y services.`, and return non-zero when any provider suspend fails.
- [ ] Run expiry tests until green.
- [ ] Commit expiry suspension slice.

### Task 8: RED Tests For Admin Provider Sync

**Files:**
- Create: `apps/backend-laravel/tests/Feature/AdminServiceProviderSyncTest.php`

- [ ] Add test: admin posts `/admin/services/{service}/sync-provider`, provider sync GET updates local status and expiry.
- [ ] Assert sync audit log action is `provider_service_sync`.
- [ ] Assert admin services index shows the sync form/button.
- [ ] Run admin sync test and verify failure is missing route/controller/view implementation.
- [ ] Commit RED admin sync tests.

### Task 9: Implement Admin Provider Sync

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceProviderSyncController.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/services/index.blade.php`

- [ ] Add admin sync controller that calls provider action service `sync`.
- [ ] Update service status from provider status when present.
- [ ] Update `expires_at` from provider expiry when present.
- [ ] Add route protected by existing `services.view` permission.
- [ ] Add sync button to admin services table.
- [ ] Run admin sync tests until green.
- [ ] Commit admin sync slice.

### Task 10: Full Verification, Docs, PR, Deploy

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-24-sprint-12-provider-lifecycle-actions.md`

- [ ] Update README with S12 provider lifecycle action routes and behavior.
- [ ] Push branch and check it out on `/opt/billing`.
- [ ] Recreate backend and run migrations.
- [ ] Run Laravel Pint and full Laravel tests on `/opt/billing`.
- [ ] Run Go `go fmt ./...`, `go vet ./...`, and `go test ./...` on `/opt/billing`.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Run `php artisan runtime:smoke-provisioning --timeout=30`.
- [ ] Mark plan verification complete, commit, and push.
- [ ] Open PR to `develop`, wait for CI, merge, delete feature branch.
- [ ] Pull `develop` on `/opt/billing`, recreate backend and worker, run smoke, and verify `/products`, `/admin/products`, `/admin/services`, and worker state.
