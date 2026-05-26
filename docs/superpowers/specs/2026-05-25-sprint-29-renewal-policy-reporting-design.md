# Sprint 29 Renewal Policy Controls and Reporting Design

## Goal

Make auto-renewal configurable per product and expose enough reporting for admins and customers to understand what will renew, what retried, and what needs action.

## Scope

Included:

- Product-level controls for whether auto-renewal is allowed.
- Product-level controls for renewal due window, retry delay, and maximum retry attempts.
- Auto-renew processor enforcement of policy before charging wallet or calling provider renewal.
- Customer toggle guardrails when a product disallows auto-renewal.
- Admin renewal report with filters, summary metrics, recent attempts, and exhausted retries.
- Customer dashboard and service list visibility for renewal status, due soon counts, failed attempts, and policy hints.

Out of scope:

- Direct provider billing or bank direct debit.
- Admin-forced auto-renew on behalf of customers.
- Manual retry reset workflow for exhausted attempts.
- Product/version snapshotting of historical renewal policy. Reports compute policy from the current product.

## Product Policy

Add columns to `products`:

- `auto_renew_allowed` boolean, default true.
- `auto_renew_window_hours` unsigned integer, default 24.
- `auto_renew_retry_delay_minutes` unsigned integer, default 60.
- `auto_renew_max_attempts` unsigned integer, default 3.

Validation:

- `auto_renew_window_hours`: 1 to 24.
- `auto_renew_retry_delay_minutes`: 5 to 10080.
- `auto_renew_max_attempts`: 1 to 20.

Services without an attached product use the same defaults so existing data stays operable.

## Processor Behavior

The auto-renew command continues to select active opted-in services expiring within the maximum supported window of 24 hours, without open cancellation requests.

Before creating or updating an attempt row, the processor evaluates the service policy:

1. If the product disallows auto-renewal, skip without charging.
2. If the service is outside the product due window, skip without creating an attempt.
3. If the latest failed attempt for the same expiry is still waiting for retry, skip.
4. If the latest failed attempt reached the product maximum attempts, treat it as exhausted and skip.
5. Re-check policy and service eligibility inside the renewal transaction precondition before charging.

On failure, `next_attempt_at` is set using the product retry delay while attempts remain. Once the max attempt count is reached, `next_attempt_at` is cleared so the attempt is terminal until the expiry target changes.

## Customer Experience

Customers can enable auto-renew only when the service is active and the product permits it. They can always disable an already-enabled service.

Customer dashboard and service list show:

- Number of active services with auto-renew enabled.
- Number of enabled services due within their policy window.
- Number of failed auto-renew attempts requiring attention.
- Per-service auto-renew state, latest attempt status, and next retry time when present.

Service detail shows policy values so the customer knows when automatic renewal starts and how many retries can happen.

## Admin Reporting

Add `/admin/renewals` under existing service admin permissions.

The report includes:

- Auto-renew enabled active services.
- Services due within their configured renewal window.
- Failed attempts in the last 7 days.
- Exhausted attempts for currently active services.
- Filterable recent attempt table by status, product, and customer email.
- Linked service and customer context for investigation.

Admin product create/edit forms expose policy fields. Product audit logging includes policy changes.

## Testing

Feature tests cover:

1. Admin product create/update stores renewal policy fields and renders them on the form.
2. Customers cannot enable auto-renew when the product disallows it, but can disable an already-enabled service.
3. Auto-renew respects a custom due window before creating an attempt.
4. Failure retry delay and maximum attempts come from product policy.
5. Admin renewal report requires admin access, renders summary metrics, and filters recent attempts.
6. Customer dashboard and service list render renewal reporting state.

Verification:

- Targeted S29 feature tests.
- Existing S28 auto-renew tests.
- Full Laravel test suite.
- Pint.
- Existing Go, Compose, and security CI checks.
