# Sprint 21 Service Cancellation Design

## Goal

Let customers request service cancellation safely and give admins an audited refund-credit shortcut from the service runbook.

## Scope

Sprint 21 adds:

- Customer cancellation request endpoint: `POST /services/{service}/cancel`.
- `service_cancellations` audit table.
- Local immediate cancellation when no provider cancel path exists.
- Provider-backed immediate cancellation queues an existing provider action job.
- End-of-period cancellation records a scheduled cancellation request without changing current service status.
- Admin service refund credit endpoint: `POST /admin/services/{service}/refund-credit`.

## Non-Goals

This sprint does not implement bank refunds, automatic pro-rata calculation, subscription proration, provider callback handling, or scheduled end-of-period execution. Scheduled cancellation execution can be added after the audit/request flow is stable.

## Cancellation Behavior

Customer request accepts:

- `mode`: `immediate` or `period_end`.
- `reason`: optional.

Rules:

- Only the service owner can request cancellation.
- Only `active` services can be cancelled.
- Duplicate open cancellation requests for the same service are ignored and redirect back.
- `period_end` creates a `scheduled` cancellation row and stores cancellation metadata on `services.meta`.
- `immediate` with provider cancel path creates a `queued` cancellation row and queues `ProviderActionJob` with idempotency key `service-cancel:{service_id}:customer-request`.
- `immediate` without provider cancel path marks service `cancelled` immediately and creates a `completed` cancellation row.

## Refund Credit Behavior

Admins with `wallets.adjust` can credit a customer's wallet from `/admin/services/{service}/refund-credit`. The credit uses the existing `WalletService`, source type `service_refund`, source id as the service id, idempotency by reference, and actor metadata.

## Testing

Feature tests cover:

- Customer immediate local cancellation.
- Customer immediate provider-backed cancellation queues provider action idempotently.
- Customer end-of-period cancellation is scheduled.
- Customer cannot cancel another user's service or a non-active service.
- Admin can credit refund from service runbook with audited ledger entry.
