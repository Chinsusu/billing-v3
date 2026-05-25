# Sprint 32 Operator/User Role Management UI Design

## Context

Sprint 31 split renewal reporting and mutation permissions, but permission assignment is still effectively code-driven through `RolesAndPermissionsSeeder`. S32 makes operator and user authorization manageable from the admin UI while keeping Spatie roles and permissions as the source of truth.

## Goals

- Add dedicated access controls for user and role management.
- Let authorized admins list users, inspect roles, create users, and assign roles or direct permissions.
- Let authorized admins create custom roles and update role permission sets.
- Audit every user or role authorization change.
- Prevent an operator from removing their own effective admin access and management permissions.

## Permissions

S32 adds three permissions:

- `users.view`: view the admin user and role management screens.
- `users.manage`: create users and update a user's roles or direct permissions.
- `roles.manage`: create roles and update role permission sets.

`super_admin` receives all three permissions. Existing operational roles do not receive them by default. This keeps user administration limited to top-level admins until explicitly delegated.

## Admin UI

Routes:

- `GET /admin/users`: searchable user list with role filter.
- `GET /admin/users/create`: create user form.
- `POST /admin/users`: create user with roles and optional direct permissions.
- `GET /admin/users/{user}`: user authorization detail.
- `GET /admin/users/{user}/edit`: role/direct permission assignment form.
- `PUT /admin/users/{user}`: update role/direct permission assignment.
- `GET /admin/roles`: role list with assigned permissions.
- `GET /admin/roles/create`: custom role form.
- `POST /admin/roles`: create custom role with permissions.
- `GET /admin/roles/{role}/edit`: permission assignment form.
- `PUT /admin/roles/{role}`: update role permission assignment.

The dashboard links to "Users & Roles" only for users with `users.view`.

## Safety Rules

- Role names must be unique and use lowercase letters, digits, dot, underscore, or hyphen.
- User role and permission assignments must reference existing records.
- Updating yourself cannot leave your effective permissions without `admin.access`, `users.manage`, and `roles.manage`.
- Updating a role assigned to yourself cannot remove your effective `admin.access`, `users.manage`, or `roles.manage`.
- S32 does not delete users, roles, or permissions.

## Audit

All mutation routes record `admin_audit_logs`:

- `user_created`
- `user_roles_updated`
- `role_created`
- `role_permissions_updated`

Audit snapshots store role and permission names, never passwords.

## Testing

Feature tests cover permission seeding, route protection, admin dashboard visibility, user creation, user role/direct permission updates, role creation, role permission updates, audit logs, and self-lockout prevention.
