# Sprint 9 Dynamic Provisioning Providers Design

## Context

Sprint 8 made the backend and worker share one Postgres runtime and proved the sandbox provisioning flow end to end. The current worker still has provider behavior compiled into Go through the sandbox processor. That does not fit the real operating model: the platform may use many provider accounts with the same API shape, each with a different endpoint, API key, account, plan catalog, or region.

Sprint 9 introduces dynamic provisioning provider configuration. Adding another account for the same provider family must be an admin configuration change, not a code change.

## Core Model

Use three separate concepts instead of one overloaded provider field:

- **Provider Driver:** API behavior family. Examples for Sprint 9 are `sandbox` and `generic_http`; provider-specific families such as `vultr`, `digitalocean`, and `linode` are out of scope unless their APIs cannot fit the generic driver.
- **Provider Account:** One concrete endpoint/account credential. Examples: `provider-a-main`, `provider-a-alt`, `vultr-account-a`, `do-sg-main`.
- **Product Mapping:** Product-level choice of provider account, plan code, region, provision path override, and options.

This supports the common case where providers have identical request/response structure and differ only by endpoint and API key. Multiple Vultr, DigitalOcean, Linode, or custom provider accounts become rows in the admin UI.

## Recommended Architecture

Laravel owns provider configuration, secret storage, and HTTP dispatch. Go owns queue orchestration, claim/retry/recovery, and calls Laravel through an internal executor endpoint.

Reasoning:

- Laravel already has encrypted casts for secrets and admin UI patterns.
- Go cannot safely decrypt Laravel encrypted DB fields without sharing app encryption internals.
- Provider config belongs in the control plane where admins manage products and accounts.
- Go remains provider-agnostic and does not need deploys for new provider accounts.

The worker flow becomes:

1. Go claims a pending provisioning job.
2. Go calls Laravel internal endpoint `POST /internal/provisioning/jobs/{job}/execute`.
3. Laravel loads the job, reads the snapshotted provider mapping, decrypts the provider account secret, executes the provider driver, and returns a normalized result.
4. Go marks the job processed or failed using its existing retry/backoff behavior.

The existing sandbox behavior moves into Laravel as a built-in `sandbox` driver so dev smoke remains zero-config.

## Provider Accounts

Add `provisioning_provider_accounts` with:

- `id` UUID
- `slug` unique stable key
- `name`
- `driver` enum-like string: `sandbox`, `generic_http`
- `base_url`
- `provision_path`
- `auth_type`: `none`, `bearer`, `header`
- `auth_header_name`
- `api_key` encrypted nullable
- `enabled`
- `timeout_seconds`
- `request_template` JSON nullable
- `response_external_id_path`
- `response_status_path`
- `response_config_path`
- `last_tested_at`, `last_test_status`, `last_test_error`
- `created_by_id`, `updated_by_id`

In Sprint 9, supported runtime drivers are:

- `sandbox`: returns the existing sandbox external ID without outbound HTTP.
- `generic_http`: sends one provision request to the configured account endpoint.

Provider-specific drivers are deferred until a provider schema cannot be represented by `generic_http`.

## Product Mapping

Products gain provider mapping fields:

- `provider_account_id` nullable foreign key
- `provider_plan_code` nullable string
- `provider_region` nullable string
- `provider_provision_path` nullable string override
- `provider_options` JSON

Admin product create/edit surfaces these fields. For active products, a provider account is required unless the product is intentionally internal-only. Sprint 9 keeps this simple: product forms allow blank provider mapping, but checkout falls back to the seeded sandbox account when no mapping is set. This preserves existing seed data and tests.

Checkout snapshots provider mapping into `provisioning_jobs.payload.product.provider`:

```json
{
  "account_slug": "provider-a-main",
  "driver": "generic_http",
  "plan_code": "A1",
  "region": "sgp",
  "provision_path": "/services/provision",
  "options": {
    "bandwidth": "1tb"
  }
}
```

The snapshot is authoritative for that job. If an admin later changes the product mapping, already queued jobs keep the mapping they were created with.

## Generic HTTP Driver

The `generic_http` driver builds a request from the account plus product snapshot:

- URL: `base_url + provision_path`, where product `provider_provision_path` overrides account `provision_path`.
- Method: `POST`.
- Auth:
  - `none`: no auth header.
  - `bearer`: `Authorization: Bearer {api_key}`.
  - `header`: `{auth_header_name}: {api_key}`.
- JSON body:

```json
{
  "idempotency_key": "service-provision:{service_id}",
  "order_id": "...",
  "service_id": "...",
  "user_id": 123,
  "product_code": "proxy-vn-30d",
  "product_type": "proxy",
  "plan_code": "A1",
  "region": "sgp",
  "duration_days": 30,
  "options": {}
}
```

Default response mapping:

- external ID: `external_id`
- status: `status`
- config: `config`

The account response path fields allow providers with the same semantics but different JSON shape to be configured without code changes. Sprint 9 supports simple dot paths such as `data.id` and `data.status`.

Success means HTTP 2xx and a non-empty external ID. Provider status values `active`, `processed`, and `success` are accepted as successful. Other status values cause the job to retry through existing worker backoff.

## Internal Security

Add `INTERNAL_PROVISIONING_TOKEN` to backend and worker runtime.

Go sends:

```text
Authorization: Bearer {INTERNAL_PROVISIONING_TOKEN}
```

Laravel rejects requests with a missing or wrong token. The token is a runtime secret in `.env`/Compose environment and is not committed as a real value. Dev Compose uses a local fixed token suitable only for dev/test.

The public web routes do not expose the internal executor endpoint.

## Admin UI

Sprint 9 adds admin pages:

- `GET /admin/provisioning-provider-accounts`
- `GET /admin/provisioning-provider-accounts/create`
- `POST /admin/provisioning-provider-accounts`
- `GET /admin/provisioning-provider-accounts/{account}/edit`
- `PUT /admin/provisioning-provider-accounts/{account}`
- `POST /admin/provisioning-provider-accounts/{account}/test`

Secrets are write-only in forms. The index/edit views show only configured/not configured and last four characters. This follows the bank integration pattern.

Product admin forms add provider account, plan code, region, provision path override, and options JSON fields. Options JSON is validated as JSON and stored as structured data.

## Go Worker Changes

Go replaces the hardcoded sandbox processor with an internal Laravel client:

- Config:
  - `BACKEND_INTERNAL_URL`, default `http://backend:8000`
  - `INTERNAL_PROVISIONING_TOKEN`
  - `PROVISIONING_EXECUTOR_TIMEOUT`, default `15s`
- Processor sends the claimed job ID to Laravel internal executor.
- Laravel returns:

```json
{
  "status": "processed",
  "external_id": "provider-service-id",
  "config": {}
}
```

Sprint 9 only persists `external_id` and active status. Provider-returned config is accepted in the internal response but not written to `services.config` in this sprint.

## Testing

Laravel tests cover:

- Admin can create/update provider accounts with encrypted secrets.
- Admin views never render raw provider secrets.
- Product forms persist provider mapping and options JSON.
- Checkout snapshots provider mapping into provisioning job payload.
- Internal executor rejects invalid token.
- Internal executor runs sandbox driver.
- Internal executor runs generic HTTP driver with a fake HTTP response.

Go tests cover:

- Processor calls Laravel internal endpoint with token and job ID.
- Processor maps success response to `provisioning.Result`.
- Processor treats non-2xx or invalid responses as retryable errors.

Runtime smoke remains:

- Seed sandbox provider account.
- Seed products mapped to sandbox.
- Run `runtime:smoke-provisioning --timeout=30`.
- Worker calls Laravel internal executor, Laravel sandbox driver returns external ID, worker marks service active.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Ensure `INTERNAL_PROVISIONING_TOKEN` exists in backend and worker Compose runtime.
3. Run migrations and seeders through backend startup.
4. Recreate backend and worker.
5. Run the runtime smoke command.

No real provider credentials are needed for Sprint 9 deployment.

## Non-Goals

Sprint 9 does not implement provider account quota, round-robin account selection, failover pools, stock/capacity tracking, provider-specific Vultr/DigitalOcean/Linode drivers, suspend/renew API calls, or async provider callback handling. Those belong after dynamic provider accounts and one-shot provisioning dispatch are stable.
