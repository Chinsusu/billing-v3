# Sprint 12 Provider Lifecycle Actions Design

Sprint 12 connects service lifecycle operations to provider APIs. Sprint 11 can read provider lifecycle dates after provisioning; Sprint 12 makes renewal, expiry suspension, cancellation, and status sync configurable without hardcoding individual providers.

## Goals

- Let admins configure provider action endpoints per product/plan.
- Keep provider API secrets in Laravel provider accounts; never send secrets to Go or store them in service metadata.
- Call provider renew before charging the customer wallet.
- Call provider suspend when `services:expire` handles an overdue service that has a suspend endpoint.
- Let admins manually sync a service status and expiry from provider.
- Record redacted audit logs for every provider lifecycle action.
- Preserve local-only behavior for sandbox products or products without action endpoints.

## Non-Goals

- Provider-specific Vultr, DigitalOcean, Linode, or custom PHP drivers.
- Moving lifecycle actions into the Go worker.
- Background queueing for renew/suspend/cancel/sync retries.
- Batch admin operations.
- Provider callbacks or webhooks.
- Customer cancellation UX.

## Product Action Configuration

Products get four nullable provider action paths:

- `provider_renew_path`
- `provider_suspend_path`
- `provider_cancel_path`
- `provider_sync_path`

Each path starts with `/` and may include placeholders:

- `{external_id}`
- `{service_id}`
- `{product_code}`
- `{plan_code}`
- `{region}`

The provider account still owns `base_url`, auth type, API key, timeout, request template, and response mapping. Product action paths are plan-specific overrides because multiple products can share the same provider account but use different service endpoints.

## Service Snapshot

Checkout snapshots provider action configuration into `services.meta.provider`:

```json
{
  "account_id": "uuid",
  "account_slug": "provider-a-main",
  "driver": "generic_http",
  "plan_code": "A1",
  "region": "sgp1",
  "provision_path": "/api/accounts/main/provision",
  "renew_path": "/api/services/{external_id}/renew",
  "suspend_path": "/api/services/{external_id}/suspend",
  "cancel_path": "/api/services/{external_id}/cancel",
  "sync_path": "/api/services/{external_id}",
  "options": {}
}
```

Existing services that do not have this snapshot fall back to the linked product provider mapping. The snapshot does not include provider API keys.

## Provider Action Client

Laravel gets a focused provider action service responsible for:

1. Resolving the provider account from service metadata or product fallback.
2. Rendering path placeholders.
3. Building a standard JSON body:

```json
{
  "action": "renew",
  "idempotency_key": "service-renewal:{service_id}:{old_expires_at}",
  "service_id": "uuid",
  "external_id": "provider-service-123",
  "product": {
    "code": "proxy-vn-30d",
    "plan_code": "A1",
    "region": "sgp1",
    "options": {}
  },
  "context": {}
}
```

4. Merging the provider account `request_template` under that body using the same pattern as provisioning.
5. Sending authenticated HTTP requests with provider account auth.
6. Writing `provisioning_execution_logs` rows with actions:
   - `provider_service_renew`
   - `provider_service_suspend`
   - `provider_service_cancel`
   - `provider_service_sync`

`renew`, `suspend`, and `cancel` use `POST`. `sync` uses `GET`. HTTP non-2xx responses fail with normalized codes such as `provider_action_http_error`. Missing action paths skip the provider call and keep local-only behavior.

## Renewal Flow

Customer renewal keeps the current local validations:

- user owns the service
- service is `active`
- service has an expiry
- renewal amount and currency can be resolved
- wallet balance is sufficient

When `provider_renew_path` is configured:

1. Lock the service and wallet in the current transaction.
2. Compute the local candidate expiry from the service lifecycle policy.
3. Call provider renew with idempotency key `service-renewal:{service_id}:{old_expires_at}`.
4. If provider returns an expiry at the configured lifecycle `expires_at_path`, use that as the new expiry.
5. If provider does not return an expiry, use the local candidate expiry.
6. Debit wallet and write the renewal metadata only after provider success.

If provider renew fails, the transaction rolls back: no wallet debit and no local expiry extension.

## Expiry Suspension

`php artisan services:expire` keeps scanning active overdue services. For each service:

1. If `provider_suspend_path` is configured, call provider suspend before local status change.
2. On provider success or missing suspend path, mark service `expired`.
3. On provider failure, leave the service `active`, write an audit log failure, count the failure, and return a non-zero command exit code.

This avoids falsely marking a service expired locally while it remains active with the provider.

## Admin Sync

Add an admin route:

- `POST /admin/services/{service}/sync-provider`

The action calls `provider_sync_path`, parses provider status with the provider account `response_status_path`, and parses provider expiry with the lifecycle `expires_at_path` when configured. Supported status mapping:

- `active`, `processed`, `success` => `active`
- `suspended`, `expired` => `expired`
- `cancelled`, `canceled` => `cancelled`

Missing status leaves local status unchanged. Missing expiry leaves local expiry unchanged. The admin services table gets a sync button for each row.

## Error Handling

Provider action failures use normalized error codes:

- `provider_action_not_configured` for required sync without a path.
- `provider_action_missing_external_id` when an action path needs an external id but the service has none.
- `provider_action_http_error` for non-2xx responses.
- `provider_action_request_failed` for transport exceptions.
- `provider_action_invalid_json` only when sync requires a JSON response.
- `provider_action_invalid_date` when a returned expiry cannot be parsed.

All request/response payloads are redacted through the existing `ProvisioningExecutionRecorder`.

## Testing

Laravel tests cover:

- Admin can store provider lifecycle action paths on a product.
- Checkout snapshots provider action paths into service metadata and provisioning payloads still work.
- Customer renewal calls provider renew before wallet debit, uses provider expiry when returned, and writes an audit log.
- Provider renewal failure does not debit the wallet and does not extend local expiry.
- `services:expire` calls provider suspend before marking an overdue service expired.
- Suspend failure leaves service active and returns command failure.
- Admin sync calls provider sync and updates service status/expiry.

Go worker tests do not change in Sprint 12.

## Deployment

After merge:

1. Pull `develop` on `/opt/billing`.
2. Recreate the backend container and run migrations.
3. Recreate the worker container even though Go code is unchanged, keeping runtime consistent.
4. Run `php artisan runtime:smoke-provisioning --timeout=30`.
5. Verify `/products`, `/admin/products`, `/admin/services`, and the worker container.
