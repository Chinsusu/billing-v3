# Sprint 30 Renewal Ops Controls Design

## Goal

Let admins act on auto-renewal failures directly from the renewal report and service runbook, with audit trails and guardrails that prevent unsafe wallet/provider side effects.

## Scope

Included:

- Admin retry-now action for failed auto-renew attempts.
- Admin reset action for exhausted failed attempts.
- Admin enable/disable auto-renew action for a service.
- Bulk retry and bulk disable actions from `/admin/renewals`.
- Required reason field for every manual operation.
- Admin audit log entries for retry, reset, toggle, and bulk operations.
- Guardrails for inactive services, expired target mismatch, open cancellation requests, and product policy disabled.

Out of scope:

- Customer-facing retry controls.
- Laravel queued jobs for renewal retry.
- Provider-specific retry overrides.
- Editing product renewal policy from the renewal report.

## Behavior

Manual retry runs through the existing `ServiceAutoRenewalProcessor`. The admin action first verifies that:

- the attempt is failed;
- the attempt targets the service current `expires_at`;
- the service is active and auto-renew is enabled;
- the service has no open cancellation request;
- the product policy still allows auto-renew;
- the service is inside the configured renewal window.

If the failed attempt is exhausted, retry lowers the attempt counter to one less than the policy max and clears `next_attempt_at`, so the processor can make exactly one immediate attempt. The processor then either marks the attempt `succeeded` or records another `failed` result with normal notification/idempotency behavior.

Reset is stricter: it is only available for failed attempts that have reached the current policy max. It resets `attempts` to `0`, clears the error, sets `next_attempt_at` to now, and leaves status as `failed`, giving the scheduler or a later manual retry a full retry budget.

Admin service auto-renew toggle:

- disabling is always allowed for the service;
- enabling requires an active service and product policy that allows auto-renew.

Bulk retry applies retry-now to selected attempt ids. Bulk disable disables auto-renew for selected services behind the selected attempt ids. Each successful row gets its own audit entry, and the bulk request gets a summary flash message.

## Routes and Permissions

Use existing `admin.access` and `services.view` permissions:

- `POST /admin/renewals/{serviceAutoRenewalAttempt}/retry`
- `POST /admin/renewals/{serviceAutoRenewalAttempt}/reset`
- `POST /admin/renewals/bulk`
- `POST /admin/services/{service}/auto-renew`

## UI

`/admin/renewals` gains:

- row checkboxes;
- a bulk action panel with required reason;
- row-level retry/reset forms for failed attempts;
- failed/exhausted state labels remain visible.

Admin service runbook gains:

- admin enable/disable auto-renew form with required reason;
- policy values already shown from Sprint 29.

## Audit

Audit actions:

- `service_auto_renew_retried`
- `service_auto_renew_reset`
- `service_auto_renew_toggled`
- `service_auto_renew_bulk_retry`
- `service_auto_renew_bulk_disable`

Audit metadata includes `reason`, `service_id`, `user_id`, `attempt_id`, and action result where relevant. Secret sanitization is handled by existing `AuditLogger`.

## Testing

Feature tests cover:

1. Admin can retry a failed current-expiry attempt and the service renews through wallet.
2. Retry rejects inactive service, product policy disabled, open cancellation, and old expiry target.
3. Admin can reset an exhausted attempt and audit reason.
4. Admin can disable and enable service auto-renew with policy guardrails.
5. Bulk retry and bulk disable apply only valid selected rows and audit each successful row.
6. Customer cannot access admin renewal ops routes.
