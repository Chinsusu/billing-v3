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

The dev Compose runtime includes a Caddy proxy on `http://localhost` and `https://localhost`, forwarding to the Laravel backend on port `8000`.

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
- `php artisan scheduled-tasks:run services_auto_renew`
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

## Sprint 17 Admin Finance Detail Workbench

Routes:

- `GET /admin/invoices/{invoice}`
- `GET /admin/orders/{order}`
- `GET /admin/payment-events/{paymentEvent}`

Admin invoice, order, and payment event lists now support focused filters and link into detail pages. Detail pages expose related customer, invoice line, order item, service, provisioning job, payment event, wallet, and ledger context without adding finance mutations; payment expiry and reconciliation remain separate automation work.

## Sprint 18 Customer Billing Activity

Routes:

- `GET /orders`
- `GET /invoices`
- `GET /wallet/top-ups`

Customer dashboard now shows wallet balance, open invoice count, active service count, recent invoices, recent orders, recent services, and recent wallet top-ups. Customer history pages are scoped to the authenticated user and link to the existing order, invoice, and QR top-up detail pages.

## Sprint 19 Payment Expiry and Reconciliation

Routes and commands:

- `POST /admin/payment-events/{paymentEvent}/reconcile-wallet`
- `php artisan payment-intents:expire`
- `php artisan scheduled-tasks:run payment_intents_expire`

Pending payment intents expire when `expires_at` has passed. Bank sandbox webhooks and private bank sync record matching late payments as `expired` payment events without crediting wallets automatically. Finance admins can reconcile `unmatched`, `rejected`, or `expired` payment events to a customer wallet from the payment event detail page; reconciliation credits the wallet once with source type `payment_event_reconciliation` and marks the event `reconciled`.

## Sprint 20 Service Runbook

Routes:

- `GET /admin/services/{service}`
- `GET /services/{service}`

Admin service detail now provides a service runbook with customer, product, order, provisioning jobs, provider action jobs, and redacted execution log context. Customer service detail shows provider action history and execution status summaries without raw provider request/response payloads.

## Sprint 21 Service Cancellation

Routes:

- `POST /services/{service}/cancel`
- `POST /admin/services/{service}/refund-credit`

Customers can request immediate or period-end cancellation for active services. Local services are cancelled immediately; provider-backed services queue an idempotent provider cancel action and keep the service active until the provider action succeeds. Cancellation requests are audited in `service_cancellations`. Admins with `wallets.adjust` can credit service refunds from the service runbook, recorded in ledger entries with `source_type=service_refund`.

## Sprint 22 Notification Outbox

Routes and commands:

- `GET /admin/notification-events`
- `GET /admin/notification-events/{notificationEvent}`
- `POST /admin/notification-events/{notificationEvent}/retry`
- `php artisan notifications:send --limit=50`
- `php artisan scheduled-tasks:run notifications_send`

Customer and operator email notifications are persisted in `notification_events` before delivery. The outbox sends pending rows with retry/backoff, lets admins inspect and retry failed notifications, and queues customer events for wallet credits, paid invoices, provisioned services, renewals, cancellations, and expiry warnings. Provider callback mismatches and exhausted provider action failures notify the configured operator mailbox.

## Sprint 23 Scheduled Cancellation Execution

Commands:

- `php artisan service-cancellations:process-scheduled`
- `php artisan scheduled-tasks:run service_cancellations_process_scheduled`

Due period-end cancellation requests are processed automatically. Local services are marked `cancelled` when their `expires_at` has passed; provider-backed services queue an idempotent provider cancel action and keep the service active until the provider worker succeeds. Successful provider cancel jobs now complete the linked `service_cancellations` row, and the scheduled processor is visible in ops health, scheduled task runs, and ops alerts.

## Sprint 24 Provider Callback Intake

Routes:

- `POST /webhooks/providers/{provisioningProviderAccount}`

Provider accounts can define a write-only callback secret and JSON paths for provider event id, external id, action, and status. Provider callbacks must send the exact JSON body signed with HMAC-SHA256 in `X-Provider-Signature` using the account callback secret.

Valid callbacks are audited in `provider_callback_events` with sensitive payload keys redacted. Duplicate provider event ids are idempotent per provider account. Matched cancel callbacks reconcile the service, provider action job, and linked cancellation row; sync and suspend callbacks update local service status where the provider status maps cleanly.

## Sprint 25 Ops Incident Workbench

Routes:

- `GET /admin/ops-alert-events/{opsAlertEvent}`
- `GET /admin/scheduled-task-runs`
- `GET /admin/scheduled-task-runs/{scheduledTaskRun}`

Admins can drill from ops alert event rows into event details with rule, delivery, actor, and context metadata. Ops health now links each scheduled task to its run history and latest run detail, while scheduled task run pages expose stored command output/error snippets for incident triage.

## Sprint 26 Notification Templates and Preferences

Routes:

- `GET /admin/notification-templates`
- `GET /admin/notification-templates/{notificationTemplate}/edit`
- `PUT /admin/notification-templates/{notificationTemplate}`
- `GET /notification-preferences`
- `POST /notification-preferences`

Admins can edit seeded email templates for customer notification types. Customer notification preferences can disable supported customer-facing notification types without suppressing operator alerts. The notification outbox renders enabled templates with `{{variable}}` placeholders and stores rendered subject/body on each event.

## Sprint 27 Admin Audit Log

Routes:

- `GET /admin/audit-logs`
- `GET /admin/audit-logs/{adminAuditLog}`

Admin audit logs capture sensitive config, money, and operations changes: bank integrations, provisioning provider accounts, products, notification templates, ops alert rules, wallet adjustments, service refund credits, provider queue actions, and provider action retries. Raw secret fields are redacted before storage; last-four helper fields remain visible for operational checks. Only users with `audit_logs.view` can inspect audit rows.

## Sprint 28 Auto-Renewal Foundation

Routes and commands:

- `POST /services/{service}/auto-renew`
- `php artisan services:auto-renew --limit=50`
- `php artisan scheduled-tasks:run services_auto_renew`

Customers can enable or disable auto-renew per active service from the service detail page. The scheduled auto-renew command renews opted-in active services expiring within 24 hours by reusing the wallet-funded `ServiceRenewalService`, so provider-backed renewals still call the provider before any wallet debit.

Each target expiry creates one `service_auto_renewal_attempts` row. Successful attempts store the renewed expiry and price snapshot. Failed attempts store the error, set `next_attempt_at` one hour later, and enqueue `service_auto_renew_failed` once per attempt count. Open cancellation requests prevent auto-renewal. Admin service runbooks show auto-renew state and attempt history, and ops health tracks the scheduled task.

## Sprint 29 Renewal Policy Controls and Reporting

Routes and commands:

- `GET /admin/renewals`
- `GET /admin/products/create`, `PUT /admin/products/{product}`
- `GET /dashboard`
- `GET /services`
- `php artisan services:auto-renew --limit=50`

Products now define auto-renew policy fields: `auto_renew_allowed`, `auto_renew_window_hours`, `auto_renew_retry_delay_minutes`, and `auto_renew_max_attempts`. Customers can only enable auto-renew when the product permits it, but can always disable an already-enabled service. The scheduler still uses wallet-funded service renewal, but it now checks each product policy before creating an attempt and again inside the renewal precondition before wallet or provider side effects.

Failed auto-renew attempts use the product retry delay while attempts remain. When an attempt reaches the product max attempts, `next_attempt_at` is cleared and the attempt is treated as exhausted until the service expiry target changes. Admins can inspect renewal summary metrics, filters, recent attempts, exhausted rows, and service links from `/admin/renewals`. Customer dashboards and service lists show enabled, due soon, failed, latest status, and next retry renewal state.

## Sprint 30 Renewal Ops Controls

Routes:

- `POST /admin/renewals/bulk`
- `POST /admin/renewals/{serviceAutoRenewalAttempt}/retry`
- `POST /admin/renewals/{serviceAutoRenewalAttempt}/reset`
- `POST /admin/services/{service}/auto-renew`

Admins can operate failed auto-renewal attempts from `/admin/renewals`: retry now, reset an exhausted attempt back to a due retry, or bulk retry/disable selected services. The service runbook also exposes an audited admin auto-renew toggle.

All renewal ops actions require a reason and write `admin_audit_logs`. Retry and reset actions are blocked when the service is not active, auto-renew is disabled, the product policy disallows auto-renew, the attempt targets an old expiry, the service is outside the policy window, or an open cancellation exists.

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
