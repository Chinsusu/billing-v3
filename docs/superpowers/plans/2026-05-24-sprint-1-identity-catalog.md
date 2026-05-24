# Sprint 1 Identity Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build Sprint 1 authentication, RBAC, product catalog, and dashboard shell in Laravel.

**Architecture:** Use Laravel session auth with small custom controllers and Blade forms. Use Spatie Permission for role/permission storage and checks. Keep Product CRUD isolated in model, form request, controller, factory, seeder, and focused feature tests.

**Tech Stack:** Laravel 13, PHP 8.4 in CI, Spatie Laravel Permission, PHPUnit feature tests, Blade.

---

### Task 1: Dependencies And RBAC Foundation

**Files:**
- Modify: `apps/backend-laravel/composer.json`
- Modify: `apps/backend-laravel/composer.lock`
- Modify: `apps/backend-laravel/app/Models/User.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Create: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`

- [x] Install `spatie/laravel-permission`.
- [x] Publish and commit Spatie permission migration/config.
- [x] Add `HasRoles` to `User`.
- [x] Register role and permission middleware aliases in `bootstrap/app.php`.
- [x] Add seeder for roles and permissions.

### Task 2: Authentication

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Auth/RegisteredUserController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Create: `apps/backend-laravel/resources/views/auth/register.blade.php`
- Create: `apps/backend-laravel/resources/views/auth/login.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/AuthFlowTest.php`

- [x] Write failing tests for register, login, logout, and guest redirects.
- [x] Implement controllers, routes, and views.
- [x] Verify tests pass.

### Task 3: Dashboard Shell

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/DashboardController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/AdminDashboardController.php`
- Create: `apps/backend-laravel/resources/views/layouts/app.blade.php`
- Create: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Test: `apps/backend-laravel/tests/Feature/DashboardAccessTest.php`

- [x] Write failing tests for customer dashboard access and admin-only access.
- [x] Implement views and role-protected admin route.
- [x] Verify tests pass.

### Task 4: Product Catalog

**Files:**
- Create: `apps/backend-laravel/database/migrations/*_create_products_table.php`
- Create: `apps/backend-laravel/app/Models/Product.php`
- Create: `apps/backend-laravel/database/factories/ProductFactory.php`
- Create: `apps/backend-laravel/app/Http/Requests/StoreProductRequest.php`
- Create: `apps/backend-laravel/app/Http/Requests/UpdateProductRequest.php`
- Create: `apps/backend-laravel/app/Http/Controllers/ProductCatalogController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/ProductController.php`
- Create: `apps/backend-laravel/resources/views/products/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/products/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/products/create.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/products/edit.blade.php`
- Test: `apps/backend-laravel/tests/Feature/ProductCatalogTest.php`

- [x] Write failing tests for product visibility and admin CRUD.
- [x] Implement migration/model/factory/controller/views.
- [x] Verify tests pass.

### Task 5: Seeds And Verification

**Files:**
- Modify: `apps/backend-laravel/database/seeders/DatabaseSeeder.php`
- Modify: `README.md`

- [x] Seed roles, permissions, admin/customer users, and sample active products.
- [x] Update README with Sprint 1 credentials and commands.
- [x] Run `php artisan test`.
- [x] Run `./vendor/bin/pint --test`.
- [x] Run Go worker checks.
- [x] Commit and push the feature branch.
