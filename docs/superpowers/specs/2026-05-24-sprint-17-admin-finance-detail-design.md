# Sprint 17 Admin Finance Detail Workbench Design

## Goal

Give admins a practical finance triage surface for invoices, orders, and payment events before adding reconciliation automation in Sprint 19.

## Scope

Sprint 17 adds read-only drill-down and light filtering:

- Admin invoice detail: `GET /admin/invoices/{invoice}`.
- Admin order detail: `GET /admin/orders/{order}`.
- Admin payment event detail: `GET /admin/payment-events/{paymentEvent}`.
- Invoice list filters by status and customer email.
- Order list filters by status and customer email.
- Payment event list filters by status and reference.
- Links from list rows and admin customer detail into the new detail pages.

## Non-Goals

This sprint does not expire payment intents, reconcile unmatched bank events, void invoices, refund payments, mutate order status, edit invoice lines, export reports, or add accounting integrations. Those belong in later finance automation sprints.

## Admin Invoice Detail

The invoice detail page shows:

- Invoice number, status, amount, currency, description, due/paid timestamps.
- Customer email with a link to admin customer detail.
- Invoice line JSON/table data.
- Related payment events.
- Related ledger entries where `source_type` is `invoice` and `source_id` is the invoice id.

## Admin Order Detail

The order detail page shows:

- Order number, status, amount, currency, paid timestamp, and customer link.
- Order items and product snapshots.
- Services created by the order with links to service runbooks.
- Provisioning jobs created for the order with links to provisioning job detail.
- Ledger entries where `source_type` is `order` and `source_id` is the order id.

## Admin Payment Event Detail

The payment event detail page shows:

- Provider, provider transaction id, reference, status, signature status, amount, currency, and processed timestamp.
- Linked payment intent, wallet, invoice, and customer context when available.
- Pretty-printed payload JSON.

## Permissions

Existing permissions stay in place:

- Invoice pages require `invoices.view`.
- Order pages require `orders.view`.
- Payment event pages require `payment_events.view`.
- Customers remain forbidden from all admin finance pages.

## Testing

Feature tests cover:

- Admin invoice detail renders customer, lines, payment events, and invoice ledger context.
- Admin order detail renders customer, items, services, provisioning jobs, and order ledger context.
- Admin payment event detail renders linked intent/wallet/invoice/customer context and payload JSON.
- Admin list pages link rows to detail pages and respect filters.
- Customer users cannot access the new detail pages.
