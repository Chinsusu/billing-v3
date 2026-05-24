# Sprint 11 Lifecycle Policy Provider Lookup Design

Sprint 11 makes service lifecycle dates configurable per product/plan and supports providers that require a second lookup request after provisioning returns an external service id.

## Goals

- Each product/plan can define its own lifecycle duration.
- The platform supports fixed-day plans and calendar-month plans.
- Calendar-month plans use no-overflow month arithmetic: January 31 + 1 month becomes the last valid day of February.
- Provider-account defaults stay out of the first implementation; product settings are the source of truth because plans under the same provider can differ.
- Provider lookup mode calls a separate lifecycle/detail endpoint after provisioning succeeds and `external_id` is known.
- Provider lookup stores provider dates in the service record through the existing Laravel executor and Go worker flow.
- Provider lookup requests and responses are recorded in `provisioning_execution_logs` with secret redaction.

## Non-Goals

- Provider renew, suspend, cancel, or resize API calls.
- Provider default lifecycle policies inherited by products.
- Per-provider custom date parser plugins.
- Async provider callbacks.
- UI polish beyond admin product configuration fields.

## Lifecycle Modes

Products get these lifecycle fields:

- `lifecycle_source`: `local_policy`, `provider_response`, or `provider_lookup`.
- `lifecycle_unit`: `day` or `calendar_month`.
- `lifecycle_count`: positive integer.
- `provider_lifecycle_path`: nullable path used by `provider_lookup`, for example `/api/services/{external_id}`.
- `provider_lifecycle_ordered_at_path`: JSON path to provider order/start date.
- `provider_lifecycle_expires_at_path`: JSON path to provider expiry date.
- `provider_lifecycle_date_format`: `iso8601`, `unix_seconds`, or `unix_ms`.
- `provider_lifecycle_timezone`: timezone used when parsing provider date values that do not carry an explicit timezone.

Existing `duration_days` remains for backward compatibility and seed data. New and edited products will write both the new lifecycle fields and `duration_days` where possible. Existing products default to:

- `lifecycle_source=local_policy`
- `lifecycle_unit=day`
- `lifecycle_count=duration_days`

## Local Date Calculation

Local lifecycle dates use a small Laravel service:

- Base date is `now()` at checkout for initial service creation.
- Renewal base date is `max(now(), services.expires_at)`.
- `day` adds `lifecycle_count` days.
- `calendar_month` adds `lifecycle_count` months using no-overflow arithmetic.

The checkout service snapshots the lifecycle policy into `services.meta.lifecycle_policy` and the provisioning job payload. Renewal uses the service snapshot so admin changes to the product do not alter already-sold services.

## Provider Lookup Flow

For `lifecycle_source=provider_lookup`:

1. Checkout creates the pending service and snapshots lifecycle settings.
2. The provisioning job payload includes the provider lifecycle lookup config.
3. Laravel generic HTTP driver provisions the service and extracts `external_id`.
4. Laravel replaces `{external_id}` in `provider_lifecycle_path`.
5. Laravel calls the lookup endpoint with the same provider account auth.
6. Laravel extracts provider `ordered_at` and `expires_at` using configured JSON paths.
7. Laravel parses dates and returns them in the internal executor response as `ordered_at` and `expires_at`.
8. Go worker persists:
   - `services.provisioned_at = ordered_at ?? now()`
   - `services.expires_at = expires_at ?? existing value`
9. Go marks the provisioning job processed.

If lookup fails, returns non-2xx, has invalid JSON, or misses required dates, provisioning fails and the job retry flow handles it. The service remains `pending_provision` until a successful retry.

## Provider Response Mode

`provider_response` is included in the model now even though the user said providers usually need lookup. It is cheap to support while touching the generic driver: provision response can contain `ordered_at` and `expires_at` paths without an extra request.

## Internal Executor Contract

Laravel currently returns:

```json
{
  "status": "processed",
  "external_id": "provider-service-123",
  "config": {}
}
```

Sprint 11 extends this to:

```json
{
  "status": "processed",
  "external_id": "provider-service-123",
  "config": {},
  "ordered_at": "2026-05-24T09:00:00+00:00",
  "expires_at": "2026-06-24T09:00:00+00:00"
}
```

Both date fields are nullable. The Go worker must remain compatible with old responses.

## Error Handling

Generic HTTP driver error codes:

- `provider_lifecycle_http_error`
- `provider_lifecycle_invalid_json`
- `provider_lifecycle_missing_ordered_at`
- `provider_lifecycle_missing_expires_at`
- `provider_lifecycle_invalid_date`

All lookup requests and responses go through `ProvisioningExecutionRecorder`, so secrets in request templates, auth headers, response payloads, and provider options remain redacted.

## Testing

Laravel feature tests cover:

- Checkout snapshots a local day lifecycle policy.
- Checkout computes a calendar-month expiry with no overflow.
- Renewal extends a calendar-month service with no overflow.
- Product admin can store provider lookup lifecycle config.
- Generic HTTP executor calls provider lifecycle lookup after provision and returns parsed dates.
- Lookup failure records an audit log and returns a 422.

Go tests cover:

- Internal executor processor decodes `ordered_at` and `expires_at`.
- Store `MarkProcessed` persists service config and provider dates.
- Store defaults `provisioned_at=now()` when ordered_at is missing and leaves expiry unchanged when expires_at is missing.
