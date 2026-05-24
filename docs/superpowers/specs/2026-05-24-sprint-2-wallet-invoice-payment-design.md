# Sprint 2 Wallet Invoice Payment Design

## Context

Sprint 2 extends the Laravel control plane from Sprint 1 with the first finance domain: wallet balances, immutable ledger entries, invoices, bank-backed payment intents, recorded payment events, and a sandbox webhook. The payment direction is provider-neutral and explicitly excludes PayOS, VNPay, and MoMo. QR payment instructions are generated from internal references and will later be wired to the private bank API.

## Scope

Build a hybrid intent-based flow:

- Customers can create wallet top-up payment intents.
- Each payment intent exposes a stable reference and QR payload for bank transfer.
- A sandbox bank webhook confirms transactions by reference.
- Valid webhook events credit the customer wallet through immutable ledger entries.
- Customers can pay open invoices from wallet balance.
- Admin users can create invoices and inspect payment events.

Sprint 2 does not integrate the real bank API, provisioning, subscriptions, refunds, partial invoice payments, tax, promotions, or external accounting exports.

## Domain Model

- `wallets`: one wallet per user and currency, with `balance_amount` stored in minor units.
- `ledger_entries`: immutable wallet movements with `credit` or `debit`, `amount`, `balance_after`, source metadata, and unique idempotency keys.
- `invoices`: customer bills with `open`, `paid`, or `void` status, integer `total_amount`, and line metadata.
- `payment_intents`: pending bank/QR payment instructions. Sprint 2 creates `wallet_topup` intents; the schema keeps `target_type` flexible for future invoice-direct intents.
- `payment_events`: append-only records of sandbox bank callbacks, including raw payload, signature status, processing status, and bank transaction id.

All money values are integer minor units. Currency is stored as an uppercase 3-letter code and Sprint 2 defaults to `VND`.

## Flow

1. A customer opens `/wallet` and creates a top-up intent with an amount.
2. The system creates the customer wallet if needed, generates a unique reference, and stores a QR payload string.
3. The customer pays by scanning/transferring with the displayed reference.
4. The sandbox webhook receives a JSON payload with `reference`, `amount`, `currency`, `transaction_id`, and `paid_at`.
5. The webhook verifies `X-Billing-Signature` as HMAC-SHA256 over the raw body using `BANK_SANDBOX_WEBHOOK_SECRET`.
6. A matching pending intent with the same amount and currency is marked `succeeded`, a payment event is stored as `accepted`, and the wallet is credited once.
7. Duplicate webhook deliveries are accepted as duplicates without changing wallet balance again.
8. Customers pay invoices from wallet balance. Payment debits the wallet, writes a ledger entry, and marks the invoice paid.

## Access Control

- Authenticated customers can view their own wallet, top-up intents, invoices, and ledger.
- Customers cannot view or pay another user's invoice or payment intent.
- Admin users need `invoices.view`, `invoices.create`, and `payment_events.view`.
- The sandbox webhook is public but requires a valid HMAC signature.

## Error Handling

- Invalid webhook signatures return HTTP 401 and do not create payment events.
- Unknown references create an `unmatched` payment event and return HTTP 202.
- Amount or currency mismatches create a `rejected` payment event and return HTTP 422.
- Duplicate bank transaction ids return HTTP 200 with duplicate status and no ledger mutation.
- Wallet debit with insufficient balance redirects back with validation errors.

## Testing

Feature tests cover:

- Creating wallet top-up intents and QR payloads.
- Signed webhook crediting the wallet and ledger.
- Duplicate webhook idempotency.
- Invalid signature rejection.
- Customer invoice payment from wallet.
- Insufficient wallet balance.
- Invoice ownership protection.
- Admin invoice creation and payment event visibility.

CI remains the existing backend, worker, infra, and secret-scan workflow.
