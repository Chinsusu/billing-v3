# Sprint 20 Service Runbook Design

## Goal

Give operators a service-centric runbook page and improve customer service detail with lifecycle/action history.

## Scope

Sprint 20 adds:

- Admin service detail page at `/admin/services/{service}`.
- Links from admin service list to service detail.
- Admin runbook context: customer, product, order, external ID, lifecycle dates, provisioning jobs, provider action jobs, and execution logs.
- Customer service detail shows provider action history and execution status without raw provider payloads.

## Non-Goals

This sprint does not add new lifecycle actions, bulk operations, arbitrary command execution, raw secret display, refunds, or cancellation policy changes.

## Authorization

- Admin detail route uses existing `services.view` permission.
- Customer detail remains scoped to owner and returns 404 for another user's service.

## Testing

Feature tests cover:

- Admin can open a service runbook and see service, customer, order, provisioning, provider action, and execution log context.
- Admin service list links to the service runbook.
- Customer service detail shows provider action history but not raw provider execution payloads.
- Customer cannot access admin service detail.
