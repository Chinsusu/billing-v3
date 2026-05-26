# Sprint 41 API Rate Limiting and Usage Logs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-key API rate limiting and safe API key usage logs.

**Architecture:** Keep rate limiting inside the existing bearer middleware through a small `ApiKeyRateLimiter` service. Persist usage metadata through an append-only `ApiKeyUsageLog` model and show recent usage on the existing API key page.

**Tech Stack:** Laravel 13, Blade, Eloquent, Cache facade, PHPUnit feature tests.

---

### Task 1: Write Failing S41 Tests

**Files:**
- Create: `apps/backend-laravel/tests/Feature/ApiKeyRateLimitUsageTest.php`

- [ ] Add a test proving successful API requests create usage logs and show recent usage on `/api-keys`.
- [ ] Add a test proving missing scopes log a `403` usage row.
- [ ] Add a test proving `rate_limit_per_minute=2` allows two requests and returns `429` on the third request.
- [ ] Run `php artisan test tests/Feature/ApiKeyRateLimitUsageTest.php --stop-on-failure` on the dev server and verify RED because `api_key_usage_logs` does not exist.
- [ ] Commit the tests.

### Task 2: Add Data Model and Config

**Files:**
- Create: `apps/backend-laravel/database/migrations/2026_05_26_090000_add_api_key_rate_limits_and_usage_logs.php`
- Create: `apps/backend-laravel/app/Models/ApiKeyUsageLog.php`
- Create: `apps/backend-laravel/config/api_keys.php`
- Modify: `apps/backend-laravel/app/Models/ApiKey.php`
- Modify: `apps/backend-laravel/app/Models/User.php`

- [ ] Add `api_keys.rate_limit_per_minute` with default 60.
- [ ] Add `api_key_usage_logs` with API key, user, prefix, route, method, path, status, error reason, IP, user agent, and `created_at`.
- [ ] Add `ApiKey::usageLogs()` and `User::apiKeyUsageLogs()` relations.
- [ ] Add casts for integer limit and status code.

### Task 3: Enforce Rate Limit and Write Logs

**Files:**
- Create: `apps/backend-laravel/app/Services/Security/ApiKeyRateLimiter.php`
- Modify: `apps/backend-laravel/app/Http/Middleware/AuthenticateApiKey.php`
- Modify: `apps/backend-laravel/app/Services/Security/ApiKeyManager.php`
- Modify: `apps/backend-laravel/app/Http/Controllers/ApiKeyController.php`

- [ ] Add `ApiKeyRateLimiter::hit(ApiKey $apiKey): array` using a per-minute cache window.
- [ ] Update `ApiKeyManager::create()` to accept a rate limit and store it.
- [ ] Validate customer-created key limits as `integer|min:1|max:config('api_keys.max_rate_limit_per_minute')`.
- [ ] In middleware, log scope failures as `403`, over-limit requests as `429`, and allowed responses with the final status code.
- [ ] Add `Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers for valid keys.

### Task 4: Update Customer UI and Docs

**Files:**
- Modify: `apps/backend-laravel/resources/views/api-keys/index.blade.php`
- Modify: `README.md`

- [ ] Add a rate-limit input to the API key create form.
- [ ] Show each key's configured limit in the existing key table.
- [ ] Add a recent usage table with key prefix, route, status, error reason, IP, and timestamp.
- [ ] Add README S41 notes listing behavior and limits.

### Task 5: Verify and Ship

- [ ] Run targeted S41 tests on the server.
- [ ] Run `ApiKeyManagementTest` to ensure S37 behavior still works.
- [ ] Run Laravel Pint.
- [ ] Run full Laravel PHPUnit.
- [ ] Run Go worker tests.
- [ ] Run Compose config/build.
- [ ] Run secret scan and `git diff --check`.
- [ ] Open PR to `develop`, wait for CI, merge, deploy `/opt/billing`, run migrations, and smoke `/api/v1/me` rate-limit behavior.
