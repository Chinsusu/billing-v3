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

Customers can renew active services from wallet balance. Renewal debits the wallet with source type `service_renewal`, extends `expires_at`, and stores renewal audit metadata on the service. Provider lifecycle API calls are added in Sprint 12.

## Sprint 6 Private Bank Integration

Routes and commands:

- `GET /admin/bank-integrations`
- `POST /admin/bank-integrations`
- `PUT /admin/bank-integrations/{bankIntegration}`
- `POST /admin/bank-integrations/{bankIntegration}/test`
- `php artisan bank:sync-payments`

Admins configure private bank endpoints and credentials in the backend UI. API keys and webhook secrets are encrypted at rest and never rendered back to the browser. The sync command expects the configured transaction endpoint to return a JSON `transactions` array with `transaction_id`, `reference`, `amount`, `currency`, and `paid_at`.

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

## Sprint 7 Worker Daemon

The Go worker can run continuously with retry backoff and stuck-job recovery:

```bash
docker run --rm --network host -v "$PWD/apps/worker-go:/app" -w /app golang:1.26.3 go run ./cmd/worker --daemon
```

Runtime environment:

- `WORKER_POLL_INTERVAL`: idle poll sleep, default `5s`.
- `PROVISIONING_STUCK_AFTER`: old `processing` job recovery threshold, default `5m`.
- `PROVISIONING_MAX_ATTEMPTS`: max processing attempts before permanent failure, default `3`.
- `PROVISIONING_RETRY_BACKOFF`: delay before retrying a failed attempt, default `60s`.
- `BACKEND_INTERNAL_URL`: Laravel internal executor base URL, default `http://localhost:8000`.
- `INTERNAL_PROVISIONING_TOKEN`: shared token sent to Laravel internal executor.
- `PROVISIONING_EXECUTOR_TIMEOUT`: HTTP timeout for executor calls, default `30s`.

The dev Compose file includes a `worker` service that runs `go run ./cmd/worker --daemon` against the Compose Postgres service.

## Sprint 9 Dynamic Provisioning Providers

Routes:

- `GET /admin/provisioning-provider-accounts`
- `POST /admin/provisioning-provider-accounts`
- `PUT /admin/provisioning-provider-accounts/{provisioningProviderAccount}`
- `POST /internal/provisioning/jobs/{provisioningJob}/execute`

Admins can define multiple provider accounts with the same driver shape but different endpoint/API key values, then map each product to a provider account, plan code, region, provision path, and JSON options. Secrets are encrypted by Laravel and never sent to the Go worker. The worker only claims jobs and calls the Laravel internal executor with `INTERNAL_PROVISIONING_TOKEN`.

## Sprint 12 Provider Lifecycle Actions

Routes and commands:

- `POST /services/{service}/renew`
- `POST /admin/services/{service}/sync-provider`
- `php artisan services:expire`

Products can define provider action paths for renew, suspend, cancel, and sync. Paths support placeholders such as `{external_id}`, `{service_id}`, `{product_code}`, `{plan_code}`, and `{region}`.

Provider-backed renewal calls the configured renew endpoint before wallet debit. If the provider returns an expiry at the configured lifecycle `expires_at` JSON path, that provider date becomes the local service expiry; if provider renew fails, the wallet is not debited and local expiry is unchanged.

Sprint 13 moves suspend, cancel, and sync to a durable provider action queue. Renew remains synchronous so wallet debit still happens only after provider renew succeeds. All provider lifecycle HTTP attempts write redacted `provisioning_execution_logs` rows.

## Sprint 13 Provider Action Queue

Routes and commands:

- `POST /admin/services/{service}/sync-provider`
- `POST /admin/services/{service}/cancel-provider`
- `GET /admin/provider-action-jobs`
- `POST /admin/provider-action-jobs/{providerActionJob}/retry`
- `php artisan provider-actions:work --once`
- `php artisan provider-actions:work --limit=50`
- `php artisan provider-actions:recover-stuck`
- `php artisan services:expire`

Overdue provider-backed services with a configured suspend path are queued as `suspend` provider action jobs and remain `active` until the worker successfully suspends them. Services without a suspend path still expire locally.

The provider action worker processes `suspend`, `cancel`, and `sync` jobs through Laravel, where encrypted provider secrets are available. Failed attempts are requeued with backoff until `max_attempts`, then marked `failed`. Stale `processing` jobs can be recovered with `provider-actions:recover-stuck`. Admins can inspect provider action jobs and retry failed jobs from `/admin/provider-action-jobs`.

## Sprint 14 Scheduler Ops Health

Routes and commands:

- `GET /admin/ops-health`
- `php artisan scheduled-tasks:run bank_sync_payments`
- `php artisan scheduled-tasks:run provider_actions_work`
- `php artisan scheduled-tasks:run services_expire`
- `php artisan scheduled-tasks:run provider_actions_recover_stuck`
- `php artisan schedule:work`

The dev Compose runtime includes a `scheduler` service that runs Laravel `schedule:work`. It records each scheduled command execution in `scheduled_task_runs` and lets admins inspect scheduler freshness, queue counts, failed jobs, overdue services, and enabled bank integrations from `/admin/ops-health`.

## Sprint 15 Ops Alerts

Routes and commands:

- `GET /admin/ops-alert-rules`
- `POST /admin/ops-alert-rules`
- `PUT /admin/ops-alert-rules/{opsAlertRule}`
- `GET /admin/ops-alert-events`
- `POST /admin/ops-alert-events/{opsAlertEvent}/acknowledge`
- `POST /admin/ops-alert-events/{opsAlertEvent}/resolve`
- `php artisan ops-alerts:evaluate`
- `php artisan scheduled-tasks:run ops_alerts_evaluate`

Admins can create enabled `ops_health` alert rules with optional webhook delivery. Webhook URLs and secrets are encrypted at rest and never rendered back to the browser. Alert evaluation persists cooldown-aware events for unhealthy scheduler tasks, queues, overdue services, and missing bank integration coverage; admins can acknowledge or resolve events from `/admin/ops-alert-events`.

## Sprint 16 Customer Wallet Admin

Routes:

- `GET /admin/customers`
- `GET /admin/customers/{user}`
- `POST /admin/customers/{user}/wallet-adjustments`

Admins and finance users can search customers, inspect wallet balances, recent ledger entries, invoices, orders, and services, then apply audited manual wallet adjustments when they have `wallets.adjust`. Support users can view customers through `customers.view` without adjustment access. Manual adjustments are recorded as `ledger_entries` with `source_type=admin_wallet_adjustment` and actor metadata.

## Sprint 18 Customer Billing Activity

Routes:

- `GET /orders`
- `GET /invoices`
- `GET /wallet/top-ups`

Customer dashboard now shows wallet balance, open invoice count, active service count, recent invoices, recent orders, recent services, and recent wallet top-ups. Customer history pages are scoped to the authenticated user and link to the existing order, invoice, and QR top-up detail pages.

## Sprint 20 Service Runbook

Routes:

- `GET /admin/services/{service}`
- `GET /services/{service}`

Admin service detail now provides a service runbook with customer, product, order, provisioning jobs, provider action jobs, and redacted execution log context. Customer service detail shows provider action history and execution status summaries without raw provider request/response payloads.

## Sprint 8 Shared Postgres Runtime

The dev Compose runtime runs Laravel and the worker against the same Postgres database:

```bash
docker compose -f infra/docker-compose.dev.yml up -d --build postgres rabbitmq redis mailpit backend worker
```

The backend service builds `apps/backend-laravel/Dockerfile.dev`, provides `pdo_pgsql`, runs Composer install, applies migrations, seeds idempotent dev data, and serves the app on port `8000`.

Run the shared-runtime smoke check with:

```bash
docker compose -f infra/docker-compose.dev.yml exec backend php artisan runtime:smoke-provisioning --timeout=30
```

The smoke command tops up the seeded customer if needed, checks out `proxy-vn-30d`, waits for the Go daemon to process the provisioning job, and verifies the service becomes `active`.

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
