# Sprint 10 Provider Execution Audit Design

## Context

Sprint 9 made provider accounts dynamic and moved provider dispatch into Laravel. That lets admins add accounts with different endpoints/API keys without redeploying Go. The next risk is operability: when a provider call fails, admins need to see which account was used, which endpoint was called, how long it took, what normalized error was returned, and what provider response mapping produced.

Sprint 10 adds auditability and a provider-account test harness before real provider APIs are connected broadly.

## Goals

- Record a redacted execution log for each provider dispatch attempt.
- Let admins test a provider account from the provider account list.
- Persist provider-returned `config` into `services.config` when Go marks a service active.
- Normalize provider errors enough for retry/debugging without leaking secrets.
- Expose execution logs in admin provisioning job views.

## Non-Goals

- Provider-specific Vultr/DigitalOcean/Linode drivers.
- Renew, suspend, cancel, or resize provider actions.
- Round-robin, quota, stock, failover pools, or capacity checks.
- Public customer visibility of provider endpoint/request/response details.
- Storing authorization headers or raw API keys in logs.

## Data Model

Add `provisioning_execution_logs`:

- `id` UUID
- `provisioning_job_id` nullable FK
- `service_id` nullable FK
- `provider_account_id` nullable FK
- `action` string, e.g. `provision_service` or `provider_account_test`
- `driver` string
- `endpoint` nullable string
- `status` string: `success` or `failed`
- `http_status` nullable integer
- `duration_ms` unsigned integer
- `error_code` nullable string
- `error_message` nullable text
- `request_payload` JSON
- `response_payload` JSON
- timestamps

Logs are append-only operational records. Existing retry behavior remains on `provisioning_jobs`; logs provide the history behind `last_error`.

## Redaction

Before storing request or response payloads, recursively redact keys whose names contain:

- `authorization`
- `api_key`
- `key`
- `token`
- `secret`
- `password`

Redacted values are stored as `***redacted***`. Headers are not stored at all. The provider API key must never appear in logs or admin views.

## Execution Flow

Laravel internal executor still owns provider dispatch.

For sandbox:

1. Resolve provider account and driver.
2. Create an execution log with action `provision_service`.
3. Return the sandbox result.
4. Mark the log `success`.

For generic HTTP:

1. Resolve provider account and endpoint.
2. Build request body from provider account template plus job snapshot.
3. Create an execution log with redacted request payload.
4. Send HTTP request with the configured auth type.
5. On HTTP failure, invalid JSON, missing external ID, or unsupported provider status, mark the log `failed` with normalized error code/message.
6. On success, mark the log `success` with HTTP status, duration, redacted response payload, external ID, and config in the normal executor response.

The internal endpoint still returns `422` for provider failures so Go's existing retry/backoff path is preserved.

## Provider Account Test Harness

Add route:

- `POST /admin/provisioning-provider-accounts/{provisioningProviderAccount}/test`

For `sandbox`, the test succeeds without outbound HTTP and writes a `provider_account_test` execution log.

For `generic_http`, the test sends a POST to the provider account endpoint using the same auth behavior and a minimal payload:

```json
{
  "action": "provider_account_test",
  "provider_account": "provider-a-main",
  "timestamp": "2026-05-24T00:00:00Z"
}
```

HTTP 2xx marks the test passed. Non-2xx, timeout, or invalid JSON marks it failed. The provider account row updates:

- `last_tested_at`
- `last_test_status`: `passed` or `failed`
- `last_test_error`

The admin list shows the current test status and provides a test button.

## Persisting Provider Config

Sprint 9's Laravel executor already returns `config`, but Go ignores it. Sprint 10 updates the Go worker:

- `provisioning.Result` gains `Config map[string]any`.
- The internal executor processor decodes `config`.
- `provisioningstore.MarkProcessed` writes `services.config` from result config while setting the service active.
- Empty or absent config writes `{}`.

This keeps provider-specific connection details attached to the service after activation.

## Admin Views

Add an admin job detail route:

- `GET /admin/provisioning-jobs/{provisioningJob}`

The existing job index links to the detail page. The detail page shows:

- Job status, attempts, idempotency key, product code, last error.
- Execution logs ordered newest first.
- For each log: provider account, action, driver, endpoint, status, HTTP status, duration, error, redacted request JSON, redacted response JSON.

Customer service pages do not show provider endpoints or raw execution payloads.

## Testing

Laravel tests cover:

- Internal executor records a successful generic HTTP execution log with redacted request/response payloads.
- Internal executor records failed generic HTTP execution logs and returns `422`.
- Admin can test a generic provider account; status fields and execution log update.
- Admin test failure updates status fields and logs the error.
- Admin job detail displays execution logs.

Go tests cover:

- Internal executor processor decodes `config`.
- Store `MarkProcessed` persists service config as JSON.
- Existing retry/failure tests still pass.

Runtime smoke remains the same but now also leaves a sandbox execution log.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Recreate backend and worker.
3. Run migrations and seeders through backend startup.
4. Run `php artisan runtime:smoke-provisioning --timeout=30`.
5. Verify `/admin/provisioning-jobs` and one job detail page render after login.
