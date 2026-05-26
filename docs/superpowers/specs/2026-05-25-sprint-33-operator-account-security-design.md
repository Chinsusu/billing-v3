# Sprint 33 Operator Account Security Design

## Context

Sprint 32 added admin UI for users, roles, and permissions. S33 hardens that surface so operators can be invited, forced to rotate passwords, disabled without deleting audit history, and reviewed through login/security activity.

## Goals

- Add admin-managed account security fields to users.
- Let authorized admins generate password setup/reset links without committing secrets or logging plaintext tokens.
- Let authorized admins require a user to change password at next login.
- Let authorized admins disable and re-enable user accounts.
- Block disabled accounts from logging in and remove access from disabled active sessions.
- Track successful login metadata and audit account security actions.
- Prevent admins from disabling themselves or disabling the last enabled `super_admin`.

## Data Model

`users` gains:

- `disabled_at`, `disabled_reason`: nullable suspension state.
- `force_password_reset_at`: nullable marker that gates authenticated pages until the user changes password.
- `invited_at`: nullable timestamp set when an admin generates a setup/reset link.
- `last_password_reset_at`: nullable timestamp set when password is changed through setup or forced reset.
- `last_login_at`, `last_login_ip`, `last_login_user_agent`: login activity metadata.

Password setup/reset links use the existing `password_reset_tokens` table. The plaintext token is displayed once in the admin flash message for test/dev use, but audit logs only record that a link was created and redact token-like fields.

## Admin UI

Routes:

- `POST /admin/users/{user}/security/send-reset-link`: create a one-time setup/reset link.
- `POST /admin/users/{user}/security/force-password-reset`: require password change on next login.
- `POST /admin/users/{user}/security/clear-force-password-reset`: clear the forced reset requirement.
- `POST /admin/users/{user}/security/disable`: disable an account with a reason.
- `POST /admin/users/{user}/security/enable`: re-enable an account.

All routes require `admin.access`, `users.view`, and `users.manage`. The user detail screen displays security status, last login activity, and action forms.

## Authentication Flows

- Disabled users cannot log in. A blocked login against a disabled account is audited.
- Disabled active sessions are logged out before reaching authenticated pages.
- Successful logins update last login metadata and record `user_login_succeeded`.
- Users with `force_password_reset_at` set can only reach logout and forced password reset routes until they set a new password.
- Guest setup/reset links validate email, token, and password confirmation before updating the password and deleting the token.

## Safety Rules

- An admin cannot disable their own account.
- A `super_admin` cannot be disabled if that would leave no enabled `super_admin`.
- Disabling a user does not delete roles, permissions, wallet data, services, invoices, or audit history.
- Passwords and tokens are never stored in admin audit payloads.

## Audit Actions

S33 records:

- `user_password_setup_link_created`
- `user_force_password_reset_required`
- `user_force_password_reset_cleared`
- `user_disabled`
- `user_enabled`
- `user_password_reset_completed`
- `user_forced_password_reset_completed`
- `user_login_succeeded`
- `user_login_blocked_disabled`

## Testing

Feature tests cover disabled login/session handling, login metadata, admin security actions, super admin safety, setup/reset token completion, forced reset gating, route permissions, and audit payload redaction.
