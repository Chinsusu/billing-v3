# Sprint 31 Renewal RBAC and Operator Safety Design

## Goal

Separate renewal reporting access from renewal mutation access so read-only operators can inspect renewal state without being able to retry wallet-funded renewals, reset attempts, bulk-disable service auto-renewal, or toggle service auto-renewal from admin tools.

## Background

Sprint 30 shipped audited renewal operations using the existing `services.view` permission. That made implementation fast, but it also means any role that can view admin services can perform actions with operational and financial side effects. S31 narrows that surface with dedicated renewal permissions while preserving the existing admin experience for authorized operators.

## Permission Model

Add two Spatie permissions:

- `renewals.view`: can access `/admin/renewals` and inspect renewal metrics, filters, attempts, and links.
- `renewals.manage`: can execute renewal operations: retry now, reset exhausted attempts, bulk retry, bulk disable, and admin service auto-renew toggle.

Role mapping:

- `super_admin`: receives both permissions.
- `ops_admin`: receives both permissions.
- `support`: does not receive renewal permissions by default.
- `finance`: does not receive renewal permissions by default.
- `customer` and `reseller`: no admin renewal permissions.

`services.view` remains the permission for the admin services index and service runbook pages. Renewal mutation routes no longer use `services.view`.

## Routes and UI

The admin renewal report route changes from `permission:services.view` to `permission:renewals.view`.

The following routes require `permission:renewals.manage`:

- `POST /admin/renewals/bulk`
- `POST /admin/renewals/{serviceAutoRenewalAttempt}/retry`
- `POST /admin/renewals/{serviceAutoRenewalAttempt}/reset`
- `POST /admin/services/{service}/auto-renew`

The admin renewal report renders bulk and row action forms only when the current user has `renewals.manage`. Users with only `renewals.view` still see the report table without checkboxes or action forms.

The service runbook renders the admin auto-renew toggle panel only when the current user has `renewals.manage`. The read-only auto-renew status and attempt history stay visible to users with `services.view`.

Admin navigation links to `/admin/renewals` should be visible only to users with `renewals.view`.

## Audit and Business Rules

S30 audit and guardrail behavior stays unchanged:

- Every mutation still requires a reason.
- Every mutation still writes `admin_audit_logs`.
- Retry/reset guardrails still block inactive services, stale expiry targets, disabled auto-renew, disabled product policy, outside-window services, and open cancellations.
- Bulk summary audit metadata continues to include selected, applied, skipped, and per-attempt metadata.

S31 changes only authorization and UI visibility, not renewal processing semantics.

## Tests

Feature tests should verify:

1. `super_admin` can view and manage renewal operations.
2. `ops_admin` can view and manage renewal operations after the seeder assigns the new permissions.
3. A user with `renewals.view` but without `renewals.manage` can access `/admin/renewals` but cannot see bulk, retry, or reset controls.
4. A user with `renewals.view` but without `renewals.manage` receives 403 on retry, reset, bulk, and admin service auto-renew routes.
5. A user with `services.view` but without `renewals.view` cannot access `/admin/renewals`.
6. A user with `services.view` but without `renewals.manage` can access the service runbook but cannot see the admin auto-renew toggle panel.
7. Existing customer authorization remains forbidden for admin renewal routes.

## Deployment

No schema migration is required. Deployment only needs the updated seeder to create and assign the new permissions. Run `php artisan db:seed --force` after deploy, then clear caches so Spatie permission cache is refreshed.
