# Sprint 33 Operator Account Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build operator account security controls for setup/reset links, forced password rotation, disabling accounts, and login activity audit.

**Architecture:** Extend `users` with security state and login metadata. Add small auth controllers for guest setup links and authenticated forced resets, plus an admin security controller for account actions. Gate disabled and forced-reset users with route middleware, and audit all account security mutations through the existing `AuditLogger`.

**Tech Stack:** Laravel 12, Blade, Spatie Laravel Permission, PHPUnit feature tests, existing admin audit log infrastructure.

---

### Task 1: Security State Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/OperatorAccountSecurityTest.php`
- Create: `apps/backend-laravel/database/migrations/2026_05_25_130000_add_security_fields_to_users_table.php`
- Modify: `apps/backend-laravel/app/Models/User.php`

- [x] Write failing tests for disabled login blocking, disabled active-session logout, successful login metadata, and audit rows.
- [x] Run the targeted test on the server and verify RED because columns and middleware do not exist.
- [x] Add the user security migration and model datetime casts.
- [x] Update login handling to block disabled accounts, update login metadata, and write audit rows.
- [x] Add account-enabled middleware for authenticated pages.
- [x] Re-run targeted tests and commit.

### Task 2: Admin Security Actions

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Admin/UserSecurityController.php`
- Modify: `apps/backend-laravel/app/Support/AdminAuthorizationSafety.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/resources/views/admin/users/show.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/users/index.blade.php`
- Modify: `apps/backend-laravel/tests/Feature/OperatorAccountSecurityTest.php`

- [x] Add failing tests for reset-link generation, force reset flagging/clearing, disable/enable, route permissions, and audit payloads.
- [x] Run the targeted test and verify RED because routes and controller do not exist.
- [x] Implement `UserSecurityController` actions with `users.manage` protection.
- [x] Add safety helpers for self-disable and last enabled `super_admin`.
- [x] Add admin user detail/index security status UI.
- [x] Re-run targeted tests and commit.

### Task 3: Password Setup and Forced Reset Flows

**Files:**
- Create: `apps/backend-laravel/app/Http/Controllers/Auth/PasswordSetupController.php`
- Create: `apps/backend-laravel/app/Http/Controllers/Auth/ForcedPasswordResetController.php`
- Create: `apps/backend-laravel/app/Http/Middleware/EnsurePasswordResetIsNotForced.php`
- Create: `apps/backend-laravel/resources/views/auth/password-setup.blade.php`
- Create: `apps/backend-laravel/resources/views/auth/forced-password-reset.blade.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`
- Modify: `apps/backend-laravel/routes/web.php`
- Modify: `apps/backend-laravel/tests/Feature/OperatorAccountSecurityTest.php`

- [x] Add failing tests for completing a setup link, rejecting invalid tokens, forced reset redirects, and forced reset completion.
- [x] Run the targeted test and verify RED because routes, views, and middleware do not exist.
- [x] Implement token creation/verification against `password_reset_tokens` without logging plaintext tokens.
- [x] Implement forced reset middleware and password update controller.
- [x] Register middleware aliases and restructure authenticated routes so logout/reset remain reachable.
- [x] Re-run targeted tests and commit.

### Task 4: Documentation and Verification

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-05-25-sprint-33-operator-account-security.md`

- [x] Document S33 routes, admin actions, safety behavior, and audit actions in `README.md`.
- [ ] Mark the plan checklist complete.
- [ ] Run targeted Laravel tests for `OperatorAccountSecurityTest`, `AdminUserRoleManagementTest`, and `AdminAuditLogTest`.
- [ ] Run full Laravel Pint and PHPUnit on the server test environment.
- [ ] Run Go worker verification and compose config/build checks.
- [ ] Run secret scan and `git diff --check`.
- [ ] Push PR, wait for CI, merge to `develop`, deploy to `/opt/billing`, migrate, and smoke login/admin security routes.
