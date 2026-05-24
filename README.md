# Billing v3

Sprint 1 foundation for a Proxy/VPS billing and provisioning platform.

## Stack

- `apps/backend-laravel`: Laravel control-plane with session auth, RBAC, dashboards, and product catalog.
- `apps/worker-go`: Go data-plane worker skeleton.
- `infra/docker-compose.dev.yml`: local PostgreSQL, RabbitMQ, Redis, and Mailpit.
- `.github/workflows/ci.yml`: backend, worker, infra, and secret-scan checks.

## Dev Server

Project path on the dev/test server:

```bash
/opt/billing
```

Start local infrastructure:

```bash
docker compose -f infra/docker-compose.dev.yml up -d
```

## Sprint 1 App

Routes:

- `GET /register`, `POST /register`
- `GET /login`, `POST /login`
- `POST /logout`
- `GET /dashboard`
- `GET /products`
- `GET /admin`
- `GET /admin/products`

Seeded accounts after `php artisan db:seed`:

```text
admin@billing.test / Password123!
customer@billing.test / Password123!
```

## Checks

Run checks with Docker toolchains:

```bash
docker run --rm -v "$PWD/apps/backend-laravel:/app" -w /app composer:2 composer install --no-interaction --prefer-dist
docker run --rm -v "$PWD/apps/backend-laravel:/app" -w /app composer:2 php artisan test
docker run --rm -v "$PWD/apps/worker-go:/app" -w /app golang:1.26.3 go test ./...
```
