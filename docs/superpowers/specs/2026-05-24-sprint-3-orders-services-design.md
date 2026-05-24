# Sprint 3 Orders Services Provisioning Design

## Context

Sprint 3 connects the Sprint 1 catalog and Sprint 2 wallet ledger to a purchasable service lifecycle. The platform still avoids real provider integration. Instead, backend order checkout creates an internal service and a provisioning outbox record that the Go worker can understand and validate.

## Scope

Build the first order-to-service path:

- Customers can order an active product from the public catalog.
- Checkout debits the customer wallet with idempotency key `order-payment:{order_id}`.
- Successful checkout creates an order, order item snapshot, service in `pending_provision`, and provisioning job in `pending`.
- Customers can view their own orders and services.
- Admin users can inspect orders, services, and provisioning jobs.
- Go worker gains a small provisioning package that validates and processes job payloads into deterministic results.

Sprint 3 does not call real proxy/VPS providers, renew services, suspend for non-payment, implement refunds, or run a long-lived RabbitMQ consumer.

## Domain Model

- `orders`: customer checkout aggregate with `pending`, `paid`, `fulfilled`, `failed`, or `cancelled` status.
- `order_items`: product snapshot with product code/name/type, quantity, unit amount, subtotal, and config snapshot.
- `services`: customer-owned purchased service with product snapshot, status, activation/expiry timestamps, and provisioning metadata.
- `provisioning_jobs`: backend outbox row for worker consumption with job type, payload, status, attempts, idempotency key, and last error.

All money remains integer minor units and defaults to `VND`.

## Flow

1. Customer submits `POST /products/{product}/order`.
2. The backend rejects inactive products and unauthenticated users.
3. `OrderCheckoutService` creates a pending order and item snapshot.
4. The service debits the wallet. If balance is insufficient, no order, service, or provisioning job is persisted.
5. The order becomes `paid`, a service becomes `pending_provision`, and a provisioning job is created with payload containing order, service, user, and product snapshot data.
6. Duplicate checkout submission for an already-created order is handled through ledger idempotency. Sprint 3 does not expose a retry endpoint.
7. Admin users inspect outbox status and can use the data to verify worker behavior.

## Worker Foundation

The Go worker adds a `provisioning` package with a `Job` payload and `Processor`. It validates required IDs, product type, and action, then returns a deterministic result. The command still starts as a lightweight worker skeleton; real RabbitMQ/Postgres consumption is deferred to the provider-integration sprint.

## Access Control

- Customers can view only their own orders and services.
- Admin users need `orders.view`, `services.view`, and `provisioning_jobs.view`.
- Public catalog remains public, but ordering requires authentication.

## Error Handling

- Insufficient wallet balance redirects back with validation error and leaves no partial order artifacts.
- Ordering inactive products returns 404.
- Admin/customer ownership violations return 403 or 404 following existing Sprint 1/Sprint 2 patterns.
- Provisioning jobs are immutable enough for audit; worker attempts update status separately in future sprints.

## Testing

Feature tests cover successful checkout, wallet debit ledger entry, insufficient balance rollback, inactive product protection, customer order/service ownership, and admin visibility. Go tests cover provisioning job validation and successful processing result.
