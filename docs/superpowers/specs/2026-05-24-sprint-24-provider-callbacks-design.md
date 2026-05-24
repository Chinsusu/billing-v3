# Sprint 24 Provider Callback Intake Design

## Goal

Accept signed provider callbacks and reconcile asynchronous provider truth back into services, provider action jobs, and service cancellation records.

## Scope

Sprint 24 adds:

- Provider account callback configuration: secret and JSON paths.
- `POST /webhooks/providers/{provisioningProviderAccount}`.
- HMAC-SHA256 signature verification through `X-Provider-Signature`.
- `provider_callback_events` audit table with redacted payloads.
- Idempotency by provider account and provider event id.
- Reconciliation for service external id, action, and status.
- Admin service runbook callback history.

## Non-Goals

This sprint does not add provider-specific SDKs, generic callback rule builders, customer notifications, broad provisioning callback automation, or financial refunds. It only normalizes common callback fields configured per provider account.

## Provider Account Callback Config

Admins can configure:

- `callback_secret`: encrypted at rest, write-only in the UI.
- `callback_event_id_path`: default `event_id`.
- `callback_external_id_path`: default `external_id`.
- `callback_action_path`: default `action`.
- `callback_status_path`: default `status`.

Secret fields are never rendered back to the browser. The UI only shows whether a callback secret is configured and the last four characters.

## Callback Endpoint

The endpoint receives JSON payloads and requires:

- Provider account is enabled.
- Callback secret is configured.
- `X-Provider-Signature` equals `hash_hmac('sha256', raw_body, callback_secret)`.

Invalid signatures return `401` and do not create audit rows. Missing callback config returns `403`.

## Audit Event

Every valid callback creates or reuses a `provider_callback_events` row with:

- provider account
- linked service when matched
- linked provider action job when matched
- provider event id
- external id
- action
- provider status
- signature status
- processing status: `processed`, `unmatched`, or `duplicate`
- redacted payload
- processed timestamp or error

If the provider event id already exists for the same provider account, the endpoint returns `duplicate` without mutating service state again.

## Reconciliation

Callbacks match a service by provider account and external id.

Supported action behavior:

- `cancel` with status `cancelled`, `canceled`, `success`, or `processed` marks the service `cancelled`, marks the latest matching provider action job `processed`, and completes linked service cancellation rows.
- `suspend` with status `suspended`, `expired`, `success`, or `processed` marks the service `expired`.
- `sync` with status `active`, `expired`, `suspended`, `cancelled`, or `canceled` updates the service status accordingly.

Unmatched callbacks are audited but do not mutate services.

## Testing

Feature tests cover:

- Valid signed cancel callback reconciles service, action job, and cancellation row.
- Duplicate provider event id is idempotent.
- Invalid signature is rejected without an audit row.
- Unmatched callback is audited without mutation.
- Admin can configure callback secret/paths without rendering the secret.
- Admin service runbook shows callback history.
