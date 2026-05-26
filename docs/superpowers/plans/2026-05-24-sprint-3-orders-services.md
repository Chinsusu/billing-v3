# Sprint 3 Orders Services Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build customer product checkout, order/service lifecycle records, provisioning outbox rows, and a Go worker provisioning validation package.

**Architecture:** Laravel owns checkout and persistence with focused models, factories, controllers, requests, and `OrderCheckoutService`. The wallet ledger remains the source of money movement. Go worker gains a small pure package for provisioning job validation without adding external provider dependencies.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, PHPUnit feature tests, Go 1.26, existing Docker/CI.

---

### Task 1: S3 Documentation

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-3-orders-services-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-3-orders-services.md`

- [x] Save the approved S3 design.
- [x] Save this implementation plan.
- [x] Scan both files for placeholders and contradictions.
- [x] Commit documentation before code.

### Task 2: Laravel RED Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/OrderCheckoutFlowTest.php`
- Create: `apps/backend-laravel/tests/Feature/AdminOperationsTest.php`

- [x] Add tests for successful product order checkout, wallet debit, service creation, and provisioning job creation.
- [x] Add tests for insufficient balance rollback and inactive product order protection.
- [x] Add tests for customer order/service ownership.
- [x] Add tests for admin orders/services/provisioning job visibility.
- [x] Run the new tests on the dev server and verify they fail because S3 models/routes do not exist.

### Task 3: Laravel Domain Implementation

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_24_030000_create_order_service_tables.php`
- Create: `apps/backend-laravel/app/Models/Order.php`
- Create: `apps/backend-laravel/app/Models/OrderItem.php`
- Create: `apps/backend-laravel/app/Models/Service.php`
- Create: `apps/backend-laravel/app/Models/ProvisioningJob.php`
- Create: `apps/backend-laravel/database/factories/OrderFactory.php`
- Create: `apps/backend-laravel/database/factories/ServiceFactory.php`
- Create: `apps/backend-laravel/app/Services/Orders/OrderCheckoutService.php`

- [x] Add migrations with UUID primary keys and user/product foreign keys.
- [x] Add models, casts, and relationships.
- [x] Add factories for tests.
- [x] Implement `OrderCheckoutService::checkout(User $user, Product $product): Order`.
- [x] Ensure checkout wraps order, wallet debit, service, and provisioning job writes in one DB transaction.

### Task 4: Laravel Routes Views Permissions

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/ProductOrderController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/OrderController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/ServiceController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/OrderController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ServiceController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProvisioningJobController.php`
- Create: `apps/backend-laravel/resources/views/orders/show.blade.php`
- Create: `apps/backend-laravel/resources/views/services/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/orders/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/services/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/provisioning-jobs/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/products/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `README.md`

- [x] Add customer order/service routes.
- [x] Add admin operations routes guarded by new permissions.
- [x] Add Blade views and product order button.
- [x] Update dashboard and README.
- [x] Run S3 Laravel tests and verify they pass.

### Task 5: Go Worker Provisioning Foundation

**Files:**
- Create: `apps/worker-go/internal/provisioning/job.go`
- Create: `apps/worker-go/internal/provisioning/processor.go`
- Create: `apps/worker-go/internal/provisioning/processor_test.go`
- Modify: `apps/worker-go/cmd/worker/main.go`

- [x] Add failing Go tests for invalid job payloads and successful provision action.
- [x] Implement `Job`, `Result`, and `Processor.Process`.
- [x] Update worker startup message to include provisioning mode.
- [x] Run Go tests and verify they pass.

### Task 6: Final Verification And Publish

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-3-orders-services.md`

- [x] Mark plan checklist complete.
- [x] Run all Laravel tests.
- [x] Run Pint.
- [x] Run Go `gofmt`, `go vet`, and `go test`.
- [x] Run Docker compose config and secret scan.
- [x] Commit, push, open PR to `develop`, wait for CI, merge, and update `/opt/billing`.
