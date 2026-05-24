# Sprint 9 Dynamic Provisioning Providers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins configure multiple provisioning provider accounts and product mappings without changing Go worker code.

**Architecture:** Laravel owns provider account CRUD, encrypted secrets, product mapping snapshots, and provider HTTP dispatch through an internal executor endpoint. Go remains the queue daemon: it claims jobs, calls Laravel's internal executor with a shared token, and applies the existing processed/failed retry flow. The built-in sandbox driver remains the default dev path.

**Tech Stack:** Laravel 13, encrypted Eloquent casts, Blade admin forms, Laravel HTTP client, Go 1.26, Docker Compose.

---

### Task 1: Documentation Checkpoint

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-9-dynamic-provisioning-providers-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-9-dynamic-provisioning-providers.md`

- [x] Save and commit the approved S9 design spec.
- [x] Save this implementation plan.
- [x] Scan spec and plan for placeholders, contradictions, and vague steps.
- [x] Commit the plan before code changes.

### Task 2: Laravel RED Tests For Provider Accounts And Product Mapping

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProvisioningProviderAccountAdminTest.php`
- Modify: `apps/backend-laravel/tests/Feature/ProductCatalogTest.php`
- Modify: `apps/backend-laravel/tests/Feature/OrderCheckoutFlowTest.php`

- [ ] Add admin test for creating provider account with encrypted secret and masked UI.
- [ ] Add admin test for updating public provider account config without overwriting blank secret.
- [ ] Add customer-forbidden test for provider account admin pages.
- [ ] Add product admin test that stores provider account, plan code, region, provision path, and options JSON.
- [ ] Add checkout test that snapshots provider mapping into `provisioning_jobs.payload.product.provider`.
- [ ] Run targeted Laravel tests on `/opt/billing` and verify they fail because tables/routes/fields do not exist.

### Task 3: Provider Account Model, Migration, Admin UI

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_080000_create_provisioning_provider_accounts_table.php`
- Create: `apps/backend-laravel/app/Models/ProvisioningProviderAccount.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningProviderAccountController.php`
- Create: `apps/backend-laravel/app/Http/Requests/StoreProvisioningProviderAccountRequest.php`
- Create: `apps/backend-laravel/app/Http/Requests/UpdateProvisioningProviderAccountRequest.php`
- Create: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/create.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/edit.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/_form.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add `provisioning_provider_accounts.manage` permission and assign it to `super_admin` and `ops_admin`.
- [ ] Add model fillable fields and encrypted `api_key` cast.
- [ ] Implement `api_key_last_four` handling when secret is present.
- [ ] Seed a `sandbox` provider account with driver `sandbox`, no API key, enabled.
- [ ] Implement admin index/create/edit/update routes and views.
- [ ] Ensure views never render raw `api_key`.
- [ ] Run provider account admin tests and fix failures.
- [ ] Commit provider account admin slice.

### Task 4: Product Mapping And Checkout Snapshot

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_081000_add_provider_mapping_to_products_table.php`
- Modify: `apps/backend-laravel/app/Models/Product.php`
- Modify: `apps/backend-laravel/app/Http/Requests/StoreProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Requests/UpdateProductRequest.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Modify: `apps/backend-laravel/resources/views/admin/products/_form.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/products/index.blade.php`
- Modify: `apps/backend-laravel/database/factories/ProductFactory.php`
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`
- Modify: `apps/backend-laravel/app/Services/Orders/OrderCheckoutService.php`

- [ ] Add nullable product mapping columns: `provider_account_id`, `provider_plan_code`, `provider_region`, `provider_provision_path`, `provider_options`.
- [ ] Cast `provider_options` to array and add relationship to `ProvisioningProviderAccount`.
- [ ] Validate product provider fields and JSON options.
- [ ] Pass provider accounts to product create/edit views.
- [ ] Store provider mapping from product forms.
- [ ] Assign seeded products to the seeded sandbox account.
- [ ] In checkout, snapshot provider mapping into job payload, falling back to sandbox account when product mapping is blank.
- [ ] Run product and checkout targeted tests and fix failures.
- [ ] Commit product mapping slice.

### Task 5: Laravel Internal Executor And Drivers

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ProvisioningInternalExecutorTest.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Internal/ProvisioningJobExecutionController.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProvisioningExecutor.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/ProvisioningResult.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/Drivers/SandboxProvisioningDriver.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/Drivers/GenericHttpProvisioningDriver.php`
- Create: `apps/backend-laravel/app/Services/Provisioning/JsonPath.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/config/services.php`
- Modify: `apps/backend-laravel/.env.example`
- Modify: `apps/backend-laravel/.env.compose.example`
- Modify: `infra/docker-compose.dev.yml`

- [ ] Add RED tests for invalid internal token, sandbox executor success, and generic HTTP executor success with `Http::fake`.
- [ ] Add `INTERNAL_PROVISIONING_TOKEN` config and Compose env for backend.
- [ ] Add internal route `POST /internal/provisioning/jobs/{provisioningJob}/execute` without CSRF.
- [ ] Implement token validation in the internal controller.
- [ ] Implement sandbox driver returning `sandbox-{product_type}-{service_id}`.
- [ ] Implement generic HTTP driver with `none`, `bearer`, and `header` auth.
- [ ] Implement simple dot-path JSON extraction for response mappings.
- [ ] Return normalized JSON: `status`, `external_id`, and `config`.
- [ ] Run executor targeted tests and fix failures.
- [ ] Commit Laravel executor slice.

### Task 6: Go Worker Internal Laravel Client

**Files:**
- Modify: `apps/worker-go/internal/config/config.go`
- Modify: `apps/worker-go/internal/config/config_test.go`
- Modify: `apps/worker-go/internal/provisioning/processor.go`
- Modify: `apps/worker-go/internal/provisioning/processor_test.go`
- Modify: `apps/worker-go/cmd/worker/main.go`
- Modify: `infra/docker-compose.dev.yml`
- Modify: `README.md`

- [ ] Add config fields for `BACKEND_INTERNAL_URL`, `INTERNAL_PROVISIONING_TOKEN`, and `PROVISIONING_EXECUTOR_TIMEOUT`.
- [ ] Replace pure sandbox processor with HTTP internal executor client.
- [ ] Preserve a unit-testable constructor for processor dependencies.
- [ ] Add Go tests for token header, success mapping, non-2xx retryable error, and invalid JSON error.
- [ ] Wire `cmd/worker` to use Laravel internal executor processor.
- [ ] Add worker Compose env: `BACKEND_INTERNAL_URL=http://backend:8000`, `INTERNAL_PROVISIONING_TOKEN=local-internal-provisioning-token`.
- [ ] Run Go targeted tests and full `go test ./...`.
- [ ] Commit Go worker client slice.

### Task 7: Runtime Smoke And Full Verification

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-9-dynamic-provisioning-providers.md`

- [ ] Push branch to GitHub and checkout it on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run `php artisan migrate --force` and `php artisan db:seed --force` through backend startup.
- [ ] Run `php artisan runtime:smoke-provisioning --timeout=30` and verify success.
- [ ] Run Laravel Pint and full tests on the dev server.
- [ ] Run Go `gofmt -l`, `go vet`, and `go test ./...` on the dev server.
- [ ] Run Docker Compose config/build and secret scan.
- [ ] Mark completed checklist items and commit plan progress.

### Task 8: PR, CI, Merge, Deploy

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-9-dynamic-provisioning-providers.md`

- [ ] Open PR to `develop`.
- [ ] Wait for CI to pass.
- [ ] Merge PR and delete feature branch.
- [ ] Pull `develop` on `/opt/billing`.
- [ ] Recreate Compose backend and worker.
- [ ] Run S9 smoke command on `/opt/billing`.
- [ ] Verify `/products`, admin provider account page, and `billing_v3_worker`.
- [ ] Mark the plan complete on `develop`, commit, push, wait for CI, and update `/opt/billing`.
