# Billing v3

Sprint 1 foundation for a Proxy/VPS billing and provisioning platform.

## Stack

- `apps/backend-laravel`: Laravel control-plane with session auth, RBAC, dashboards, product catalog, wallet, invoices, and bank webhook sandbox.
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

## Sprint 2 Finance

Routes:

- `GET /wallet`
- `POST /wallet/top-ups`
- `GET /wallet/top-ups/{paymentIntent}`
- `GET /invoices/{invoice}`
- `POST /invoices/{invoice}/pay`
- `GET /admin/invoices`, `POST /admin/invoices`
- `GET /admin/payment-events`
- `POST /webhooks/bank/sandbox`

## Sprint 3 Operations

Routes:

- `POST /products/{product}/order`
- `GET /orders/{order}`
- `GET /services`
- `GET /admin/orders`
- `GET /admin/services`
- `GET /admin/provisioning-jobs`

Customer checkout debits wallet with idempotency key `order-payment:{order_id}`, creates a service in `pending_provision`, and writes a `provision_service` outbox row in `provisioning_jobs`.

## Sprint 4 Provisioning Execution

Routes:

- `GET /services/{service}`
- `POST /admin/provisioning-jobs/{provisioningJob}/retry`

The Go worker can process one pending provisioning job with:

```bash
docker run --rm --network host -v "$PWD/apps/worker-go:/app" -w /app golang:1.26.3 go run ./cmd/worker --once
```

S4 uses the sandbox processor only: pending jobs are claimed, services are marked `active` with a sandbox external ID on success, and failed jobs can be requeued by an admin without resetting attempts.

## Sprint 5 Service Lifecycle

Routes and commands:

- `POST /services/{service}/renew`
- `php artisan services:expire`

Customers can renew active services from wallet balance. Renewal debits the wallet with source type `service_renewal`, extends `expires_at`, and stores renewal audit metadata on the service. The expiry command marks overdue active services as `expired`; provider renew/suspend API calls are still out of scope.

Sandbox webhook payload:

```json
{
  "reference": "TOPUP-20260524-ABCDEFGH",
  "amount": 150000,
  "currency": "VND",
  "transaction_id": "BANK-TXN-0001",
  "paid_at": "2026-05-24T09:00:00+07:00"
}
```

Sign the exact JSON body with HMAC-SHA256 and send the hex digest in `X-Billing-Signature` using `BANK_SANDBOX_WEBHOOK_SECRET`.

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
