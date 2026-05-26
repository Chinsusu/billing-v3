# Sprint 41 API Rate Limiting and Usage Logs Design

## Goal

Harden customer API keys by enforcing a per-key request limit and recording safe usage metadata for customer and operator troubleshooting.

## Scope

S41 covers only the first-party API key surface introduced in S37. Reseller settlement and commission reporting are a separate accounting workflow and should be handled as S42.

## Approach

Each API key gets a `rate_limit_per_minute` value. New keys default to `config('api_keys.default_rate_limit_per_minute')`, and customer-created keys can choose a value up to `config('api_keys.max_rate_limit_per_minute')`. The bearer middleware enforces the limit after key validation and scope validation, then adds standard rate-limit headers to API responses.

Every valid API key attempt records one `api_key_usage_logs` row. The log stores API key id, user id, prefix, route name, method, path, status code, error reason, IP, and user agent. It never stores the raw bearer token or key hash.

## Data Model

- `api_keys.rate_limit_per_minute`: integer, default 60.
- `api_key_usage_logs`: append-only request metadata.
- `ApiKey` has many `ApiKeyUsageLog` records.

Logs are retained in the database for now; retention can be added later with a dedicated purge command once usage volume is known.

## Behavior

- Missing or invalid bearer tokens still return `401` and are not logged because no key identity is known.
- Valid key with missing required scope returns `403` and logs `error_reason=missing_scope`.
- Valid key over its minute limit returns `429`, logs `error_reason=rate_limited`, and includes `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset`.
- Allowed requests update `last_used_at`, log the final response status, and include the same rate-limit headers.

## UI

The customer API key page shows each key's per-minute limit and a recent usage table for the authenticated user's keys. This keeps the feature useful without adding a new admin screen.

## Tests

Feature tests cover:

- Successful API calls create usage logs and display recent usage.
- Scope failures create `403` usage logs.
- Per-key limits produce `429` after the configured number of requests and include rate-limit headers.
