# Sprint 8 Postgres Runtime Worker Design

## Context

Sprint 7 made the Go provisioning worker deployable as a daemon, but the dev server still runs Laravel against SQLite while the worker connects to the Docker Compose Postgres database. That split means the worker cannot process jobs created by the web app on the dev server.

Sprint 8 aligns the dev runtime so Laravel and the Go worker share the same Postgres database, then enables the worker daemon as part of the Compose runtime.

## Goal

Make `/opt/billing` run the web app and worker against one shared Postgres database in dev/test:

- Laravel backend uses Postgres in Docker Compose.
- Go worker daemon uses the same Postgres database.
- Migrations and idempotent seed data run before the backend is marked healthy.
- Worker starts only after Postgres is healthy and backend migrations have completed.
- End-to-end smoke proves an order creates a provisioning job and the worker activates the service.

## Non-Goals

Sprint 8 does not add real proxy/VPS provider APIs, production supervisor/systemd deployment, database backup/restore tooling, zero-downtime migration tooling, or queue/RabbitMQ consumption. CI can keep SQLite for the full Laravel suite; Sprint 8 adds a focused Postgres runtime smoke check instead of replacing every CI test with Postgres.

## Runtime Architecture

Docker Compose becomes the source of truth for the dev/test runtime:

- `postgres`: existing Postgres 16 service with healthcheck.
- `backend`: new Laravel dev service built from a project Dockerfile with `pdo_pgsql`.
- `worker`: existing Go daemon service, updated to wait for backend health.
- `rabbitmq`, `redis`, and `mailpit`: unchanged infrastructure services.

The backend service mounts `apps/backend-laravel:/app`, runs `composer install --no-interaction --prefer-dist`, runs `php artisan migrate --force`, runs the idempotent `DatabaseSeeder`, clears config cache, then starts `php artisan serve --host=0.0.0.0 --port=8000`.

The worker service mounts `apps/worker-go:/app` and runs `go run ./cmd/worker --daemon` with the same Postgres DSN.

## Backend Dev Image

Create `apps/backend-laravel/Dockerfile.dev` based on PHP CLI with:

- `pdo_pgsql` for runtime Postgres access.
- Composer copied from the official Composer image.
- common packages needed for Composer installs, such as `git` and `unzip`.

The image is intentionally dev/test only. It does not replace a future production PHP-FPM/Nginx image.

## Configuration

Keep `.env.example` SQLite-friendly for local PHP and CI defaults. Add a dedicated Postgres runtime example, such as `apps/backend-laravel/.env.compose.example`, with:

- `DB_CONNECTION=pgsql`
- `DB_HOST=postgres`
- `DB_PORT=5432`
- `DB_DATABASE=billing_v3`
- `DB_USERNAME=billing`
- `DB_PASSWORD=billing_secret`
- Redis and mail host values matching Compose service names.

The dev server can keep its existing `.env` secrets and app key. Compose environment variables override only runtime service connection values, so no real bank API keys or webhook secrets are committed.

## Startup Ordering

Startup order must prevent the worker from querying missing tables:

1. `postgres` reaches healthy state.
2. `backend` runs Composer install, migrations, and seed.
3. `backend` healthcheck passes against `http://127.0.0.1:8000/products`.
4. `worker` starts daemon mode.

The backend migration/seed step must not use `migrate:fresh` by default. It uses normal `migrate --force` plus idempotent seeders so restarting Compose does not wipe dev data.

## End-To-End Smoke

Add a server-friendly smoke script or documented command sequence that proves the shared runtime works:

1. Reset or migrate the Postgres dev database.
2. Ensure seeded admin and customer users exist.
3. Create a customer wallet balance.
4. Place an order for an active product.
5. Confirm a `provisioning_jobs` row exists in Postgres.
6. Wait for the worker daemon to process it.
7. Confirm the related service becomes `active` with a sandbox external ID.

The smoke should run through Laravel artisan/database APIs for state changes and database assertions. HTTP checks are limited to verifying that `/products` returns a successful response.

## CI Coverage

Existing CI remains:

- Laravel full test suite on SQLite.
- Go `gofmt`, `go vet`, and `go test`.
- Compose config validation.
- secret scan.

Sprint 8 adds focused runtime validation:

- build the backend dev image.
- run Docker Compose config.
- run a Postgres migration smoke, either in GitHub Actions with a Postgres service or through Docker Compose on the dev server.

If GitHub Actions runtime cost becomes a concern, the Postgres smoke can be a script verified on the dev server and documented, while CI at minimum validates image build and Compose config.

## Deployment To Dev Server

Deploy flow after merge:

1. Pull `develop` on `/opt/billing`.
2. Stop and remove the old ad-hoc `billing_v3_backend` container if it exists.
3. Build and start Compose services: `postgres`, `rabbitmq`, `redis`, `mailpit`, `backend`, and `worker`.
4. Run the S8 smoke check.
5. Verify `/products` returns 200 and `billing_v3_worker` remains running.

Dev/test data reset remains a separate explicit command and is not part of the default deploy flow.

## Testing

Test coverage should include:

- Dockerfile/Compose config validation.
- backend Postgres migration smoke.
- worker daemon service starts after backend health.
- end-to-end provisioning smoke against shared Postgres.
- existing Laravel and Go suites unchanged.

## Risks

- Laravel migrations may have hidden SQLite assumptions. The Postgres smoke is required to catch those.
- Composer install on container start is slower than a production image, but acceptable for dev/test and keeps the workflow simple.
- The worker is still sandbox-provider only; service activation proves orchestration, not real provider provisioning.
