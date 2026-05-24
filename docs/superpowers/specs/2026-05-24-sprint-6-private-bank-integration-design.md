# Sprint 6 Private Bank Integration Design

## Context

Sprint 2 created wallet top-up intents and a sandbox bank webhook. Sprint 5 completed the first service lifecycle loop, so the next useful production step is replacing manual sandbox payment confirmation with a private bank integration. The user explicitly does not want PayOS, VNPay, or MoMo.

Sprint 6 adds admin-managed bank configuration without exposing secrets to browser runtime. Admin screens collect credentials, but only the Laravel backend stores, decrypts, and uses those credentials.

## Scope

Build private bank integration foundation:

- Admins can configure one or more bank integrations from `/admin/bank-integrations`.
- Configuration includes provider name, base URL, transaction path, account number, enabled flag, API key, and webhook secret.
- Secrets are write-only in the UI and encrypted at rest using Laravel encryption casts.
- The UI shows only secret status and last 4 characters when available.
- Admins can rotate API key or webhook secret by submitting a new value; blank secret fields keep the existing encrypted value.
- Admins can run a test connection action from the UI.
- `php artisan bank:sync-payments` fetches bank transactions and applies matching wallet top-ups.
- Payment sync creates `payment_events` with provider `private_bank`.
- Accepted transactions credit the wallet exactly once through ledger idempotency.
- Unknown references are stored as `unmatched`.
- Amount or currency mismatches are stored as `rejected`.
- Duplicate transaction IDs do not mutate wallet balance again.
- Existing sandbox webhook remains available for local testing.

Sprint 6 does not implement the final bank API schema if it differs from the adapter contract, browser-side secret storage, real-time polling daemons, payout/refund flows, or provider proxy/VPS integration.

## Bank API Adapter Contract

Because the exact bank API schema is not in the repository yet, Sprint 6 defines a narrow adapter contract for the first implementation. The configured `base_url` and `transactions_path` are combined into a GET request. The backend sends:

- `Accept: application/json`
- `Authorization: Bearer {api_key}` when an API key is configured

Expected response:

```json
{
  "transactions": [
    {
      "transaction_id": "BANK-TXN-0001",
      "reference": "TOPUP-20260524-ABCDEFGH",
      "amount": 150000,
      "currency": "VND",
      "paid_at": "2026-05-24T09:00:00+07:00"
    }
  ]
}
```

If the real bank returns a different shape, only the private bank client mapping should change. Payment matching, ledger movement, and event recording stay stable.

## Domain Model

Add `bank_integrations`:

- `provider`: stable slug, default `private_bank`.
- `name`: admin label.
- `base_url`: bank API base URL.
- `transactions_path`: path used by sync command.
- `account_number`: receiving account identifier.
- `enabled`: whether sync should use this integration.
- `api_key`: encrypted cast, write-only in UI.
- `webhook_secret`: encrypted cast, write-only in UI.
- `api_key_last_four` and `webhook_secret_last_four`: display-safe indicators.
- `last_tested_at`, `last_sync_at`, `last_sync_status`, `last_sync_error`: operational state.
- `created_by_id`, `updated_by_id`: audit user references.

No bank credential is committed to `.env`, docs, tests, or source. `.env` may still hold `APP_KEY`, which Laravel encryption requires.

## Flow

### Admin Configuration

1. Admin opens `/admin/bank-integrations`.
2. Admin creates or edits an integration.
3. Backend validates URL/path/account/provider fields.
4. If a secret field is blank, the existing encrypted secret is preserved.
5. If a secret field has a value, backend encrypts it and updates the last-four marker.
6. List/detail views never render the full secret.

### Test Connection

1. Admin clicks `Test Connection`.
2. Backend calls the configured bank endpoint using backend-only credentials.
3. Success stores `last_tested_at`, `last_sync_status=tested`, and clears `last_sync_error`.
4. Failure stores `last_sync_status=failed` and a short error message.

### Payment Sync

1. Operator runs `php artisan bank:sync-payments`.
2. Command loads enabled bank integrations.
3. For each integration, backend fetches transactions.
4. Each transaction is matched by `reference` to a pending `payment_intent`.
5. Accepted transactions create a `payment_event`, credit wallet, and mark intent `succeeded`.
6. Unknown references create `unmatched` events and do not credit wallet.
7. Amount/currency mismatches create `rejected` events and do not credit wallet.
8. Duplicate transaction IDs are counted as duplicates and skipped.
9. Command prints accepted/rejected/unmatched/duplicate counts.

## Access Control

- Bank integration UI requires `bank_integrations.manage`.
- `super_admin` and `finance` roles get the permission.
- Customers cannot access bank integration routes.
- Sync command is CLI-only in Sprint 6.

## Error Handling

- HTTP failures during sync mark the integration `failed` and continue to the next integration.
- Malformed bank responses mark the integration `failed` and do not mutate wallet or payment intents.
- Duplicate provider transaction IDs do not create another ledger entry.
- Invalid admin form submissions redirect back with validation errors.

## Testing

Laravel feature tests cover:

- Admin can create bank integration and secrets are encrypted.
- Admin can update public config without overwriting blank secret fields.
- Admin UI masks configured secrets and never renders raw secret values.
- Customers cannot access bank integration admin pages.
- Test connection uses backend HTTP client and stores status.
- Payment sync accepts matching private bank transactions and credits wallet once.
- Payment sync records unmatched and rejected transactions.
- Payment sync skips duplicates without double credit.

No Go worker changes are required in Sprint 6.
