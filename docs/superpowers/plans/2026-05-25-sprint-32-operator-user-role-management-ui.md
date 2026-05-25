# Sprint 32 Operator/User Role Management UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build admin UI and audited workflows for user role assignment, direct permissions, and role permission management.

**Architecture:** Keep Spatie roles and permissions as the authorization backend. Add thin admin controllers for users and roles, guarded by new `users.view`, `users.manage`, and `roles.manage` permissions. Mutations use `AuditLogger` snapshots and self-lockout guard helpers before syncing assignments.

**Tech Stack:** Laravel 12, Blade, Spatie Laravel Permission, PHPUnit feature tests, existing admin audit log infrastructure.

---

### Task 1: Permission Seed and Route Protection Tests

**Files:**
- Modify: `apps/backend-laravel/tests/Feature/AdminUserRoleManagementTest.php`
- Modify: `apps/backend-laravel/database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`

- [ ] Add tests proving `super_admin` receives `users.view`, `users.manage`, and `roles.manage`; `ops_admin` does not.
- [ ] Add tests proving `/admin/users` and `/admin/roles` are forbidden without `users.view`.
- [ ] Add tests proving the dashboard shows "Users & Roles" only with `users.view`.
- [ ] Run `php artisan test tests/Feature/AdminUserRoleManagementTest.php --filter=permission` and verify RED before implementation.
- [ ] Add permissions to `RolesAndPermissionsSeeder`, grant them through the super admin all-permission list only.
- [ ] Register admin user and role routes with correct middleware.
- [ ] Add dashboard link gated by `@can('users.view')`.
- [ ] Re-run targeted tests and commit.

### Task 2: Admin User Management

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/UserController.php`
- Create: `apps/backend-laravel/resources/views/admin/users/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/users/show.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/users/create.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/users/edit.blade.php`
- Modify: `apps/backend-laravel/tests/Feature/AdminUserRoleManagementTest.php`

- [ ] Add tests for listing users with role filter and authorization detail.
- [ ] Add tests for creating a user with roles and direct permissions.
- [ ] Add tests for updating a user's roles and direct permissions.
- [ ] Add tests for `user_created` and `user_roles_updated` audit rows with role and permission names.
- [ ] Verify these tests fail because the controller and views do not exist.
- [ ] Implement `UserController` index, show, create, store, edit, and update.
- [ ] Validate role and permission names against existing Spatie records.
- [ ] Record audit logs without storing the submitted password.
- [ ] Build simple Blade screens using existing `.panel`, `.grid`, table, checkbox, and button styles.
- [ ] Re-run targeted tests and commit.

### Task 3: Admin Role Management

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/RoleController.php`
- Create: `apps/backend-laravel/resources/views/admin/roles/index.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/roles/create.blade.php`
- Create: `apps/backend-laravel/resources/views/admin/roles/edit.blade.php`
- Modify: `apps/backend-laravel/tests/Feature/AdminUserRoleManagementTest.php`

- [ ] Add tests for role list visibility.
- [ ] Add tests for creating a custom role with permissions.
- [ ] Add tests for updating role permissions.
- [ ] Add tests for `role_created` and `role_permissions_updated` audit rows.
- [ ] Verify these tests fail before implementation.
- [ ] Implement `RoleController` index, create, store, edit, and update.
- [ ] Validate role names with `^[a-z0-9_.-]+$`, unique in Spatie roles, guard name `web`.
- [ ] Build Blade screens matching existing admin table/form patterns.
- [ ] Re-run targeted tests and commit.

### Task 4: Self-Lockout Guard

**Files:**
- Create: `apps/backend-laravel/app/Support/AdminAuthorizationSafety.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/UserController.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/Admin/RoleController.php`
- Modify: `apps/backend-laravel/tests/Feature/AdminUserRoleManagementTest.php`

- [ ] Add tests proving an admin cannot update themselves into losing `admin.access`, `users.manage`, or `roles.manage`.
- [ ] Add tests proving an admin cannot update a role assigned to themselves if the effective result loses one of those critical permissions.
- [ ] Verify these tests fail before implementation.
- [ ] Implement `AdminAuthorizationSafety` helper that evaluates effective permission names from role permission sets plus direct permission names.
- [ ] Use the helper in user and role update paths before syncing changes.
- [ ] Return validation errors to the form instead of partially applying changes.
- [ ] Re-run targeted tests and commit.

### Task 5: Documentation and Verification

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-32-operator-user-role-management-ui.md`

- [ ] Document S32 routes, permissions, safety behavior, and audit actions in `README.md`.
- [ ] Mark plan checklist complete.
- [ ] Run targeted Laravel tests for `AdminUserRoleManagementTest` and `AdminAuditLogTest`.
- [ ] Run full Laravel Pint and PHPUnit on the server test environment.
- [ ] Run Go worker verification and compose config/build checks.
- [ ] Run secret scan and `git diff --check`.
- [ ] Push PR, wait for CI, merge to `develop`, deploy to `/opt/billing`, seed permissions, and smoke `/admin/users` and `/admin/roles`.
