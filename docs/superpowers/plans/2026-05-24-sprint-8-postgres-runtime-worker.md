# Sprint 8 Postgres Runtime Worker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Run Laravel and the Go worker against the same Docker Compose Postgres database, then enable the daemon worker on the dev server.

**Architecture:** Docker Compose owns the dev/test runtime. A Laravel dev image provides `pdo_pgsql`; the backend service migrates and seeds Postgres before becoming healthy; the Go worker waits for backend health before starting daemon mode. A Laravel smoke command proves checkout-to-provisioning flow against the shared database.

**Tech Stack:** Laravel 13, PHP CLI dev image, `pdo_pgsql`, Docker Compose, Postgres 16, Go 1.26 worker.

---

### Task 1: S8 Documentation Checkpoint

**Files:**
- Create: `docs/superpowers/specs/2026-05-24-sprint-8-postgres-runtime-worker-design.md`
- Create: `docs/superpowers/plans/2026-05-24-sprint-8-postgres-runtime-worker.md`

- [x] Save and commit the approved S8 design spec.
- [x] Save this implementation plan.
- [x] Scan the spec and plan for placeholders, contradictions, and vague steps.
- [x] Commit the plan before code changes.

### Task 2: RED Runtime Smoke Command Test

**Files:**
- Create: `apps/backend-laravel/tests/Feature/RuntimeSmokeCommandTest.php`
- Create: `apps/backend-laravel/app/Console/Commands/SmokeProvisioningRuntimeCommand.php`
- Modify: `apps/backend-laravel/bootstrap/app.php`

- [x] Add a failing Laravel feature test for the smoke command.

```php
public function test_smoke_command_creates_a_pending_provisioning_job_when_worker_is_not_running(): void
{
    $this->seed(DatabaseSeeder::class);

    $this->artisan('runtime:smoke-provisioning', ['--timeout' => 0])
        ->expectsOutputToContain('Created smoke order')
        ->expectsOutputToContain('Timed out waiting for service provisioning')
        ->assertExitCode(1);

    $this->assertDatabaseHas('provisioning_jobs', [
        'type' => 'provision_service',
        'status' => 'pending',
    ]);
}
```

- [x] Run the targeted test on the dev server and verify it fails because the command is not registered.

```bash
docker run --rm --entrypoint sh -v /opt/billing/apps/backend-laravel:/app -w /app composer:2 -lc 'composer install --no-interaction --prefer-dist >/tmp/composer-install.log && php artisan test --filter=RuntimeSmokeCommandTest'
```

- [x] Implement `SmokeProvisioningRuntimeCommand` with signature `runtime:smoke-provisioning {--timeout=30}`.

```php
protected $signature = 'runtime:smoke-provisioning {--timeout=30 : Seconds to wait for the worker daemon}';
```

The command must seed prerequisites through existing data, credit the seeded customer wallet when needed, checkout `proxy-vn-30d`, find the created service and provisioning job, poll until the service is `active`, and return exit code `1` with useful status if timeout expires.

- [x] Register the command in `bootstrap/app.php` under `->withCommands([...])`.
- [x] Run the targeted test again and verify it passes.
- [x] Commit the smoke command and test.

### Task 3: Backend Dev Image And Compose Runtime

**Files:**
- Create: `apps/backend-laravel/Dockerfile.dev`
- Create: `apps/backend-laravel/.dockerignore`
- Create: `apps/backend-laravel/.env.compose.example`
- Modify: `infra/docker-compose.dev.yml`

- [x] Add `Dockerfile.dev` for the backend dev runtime.
- [x] Add `.dockerignore` so backend Docker builds do not send `.env`, `vendor`, or `node_modules` in the context.

```dockerfile
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev curl \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

- [x] Add `.env.compose.example` with Postgres, Redis, and Mailpit service names.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=billing_v3
DB_USERNAME=billing
DB_PASSWORD=billing_secret
REDIS_HOST=redis
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

- [x] Add `backend` service to `infra/docker-compose.dev.yml`.

```yaml
backend:
  build:
    context: ../apps/backend-laravel
    dockerfile: Dockerfile.dev
  container_name: billing_v3_backend
  working_dir: /app
  command: ["sh", "-lc", "composer install --no-interaction --prefer-dist && php artisan config:clear && php artisan migrate --force && php artisan db:seed --force && php artisan serve --host=0.0.0.0 --port=8000"]
  environment:
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_PORT: 5432
    DB_DATABASE: billing_v3
    DB_USERNAME: billing
    DB_PASSWORD: billing_secret
    REDIS_HOST: redis
    MAIL_MAILER: smtp
    MAIL_HOST: mailpit
    MAIL_PORT: 1025
    BANK_SANDBOX_WEBHOOK_SECRET: local-bank-sandbox-secret
  ports:
    - "8000:8000"
  volumes:
    - ../apps/backend-laravel:/app
  depends_on:
    postgres:
      condition: service_healthy
  healthcheck:
    test: ["CMD-SHELL", "curl -fsS http://127.0.0.1:8000/products >/dev/null"]
```

- [x] Update `worker.depends_on` to wait for `backend` service health in addition to Postgres health.
- [x] Run `docker compose -f infra/docker-compose.dev.yml config` on the dev server.
- [x] Build the backend image on the dev server.
- [x] Commit the Docker runtime changes.

### Task 4: CI And Documentation

**Files:**
- Modify: `.github/workflows/ci.yml`
- Modify: `README.md`

- [x] Update the `infra` CI job to build the backend dev image.

```yaml
- run: docker compose -f infra/docker-compose.dev.yml config
- run: docker compose -f infra/docker-compose.dev.yml build backend
```

- [x] Document S8 runtime commands in `README.md`.

```bash
docker compose -f infra/docker-compose.dev.yml up -d --build postgres rabbitmq redis mailpit backend worker
docker compose -f infra/docker-compose.dev.yml exec backend php artisan runtime:smoke-provisioning --timeout=30
```

- [x] Run local diff checks and commit CI/docs updates.

### Task 5: Dev Server Runtime Verification

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-8-postgres-runtime-worker.md`

- [x] Push the branch to GitHub.
- [x] Checkout the S8 branch on `/opt/billing`.
- [x] Stop and remove the old ad-hoc `billing_v3_backend` container.

```bash
docker rm -f billing_v3_backend || true
```

- [x] Start full Compose runtime.

```bash
docker compose -f infra/docker-compose.dev.yml up -d --build postgres rabbitmq redis mailpit backend worker
```

- [x] Verify backend and worker containers are running.
- [x] Run `php artisan runtime:smoke-provisioning --timeout=30` inside the backend container and verify exit code `0`.
- [x] Verify `/products` returns a successful response.
- [x] Run Laravel full test suite and Go full test suite on the dev server.
- [x] Run Docker Compose config and secret scan.
- [x] Mark completed checklist items and commit the plan progress.

### Task 6: PR, CI, Merge, Deploy

**Files:**
- Modify: `docs/superpowers/plans/2026-05-24-sprint-8-postgres-runtime-worker.md`

- [ ] Open PR to `develop`.
- [ ] Wait for CI to pass.
- [ ] Merge PR and delete feature branch.
- [ ] Pull `develop` on `/opt/billing`.
- [ ] Recreate Compose backend and worker on the dev server.
- [ ] Run S8 smoke command on `/opt/billing`.
- [ ] Verify `/products` and `billing_v3_worker`.
- [ ] Mark the plan complete on `develop`, commit, push, wait for CI, and update `/opt/billing`.
