# Payment and refund reconciliation

RAOZA treats the configured payment provider as authoritative for both payment and refund outcomes. An internal refund request is not proof that money was returned.

## Refund lifecycle

1. An authorized Admin user requests a positive amount against the order's single successful provider payment.
2. RAOZA reserves that amount while the refund is `requested` or `processing`, preventing concurrent refunds from exceeding the paid amount.
3. RAOZA looks for an existing provider refund using a stable, non-secret reference. If none exists, it creates the refund with the same provider idempotency key and stores the provider refund ID.
4. Provider `queued`, `pending`, and `processing` states remain internally `processing`; `refunded` becomes `succeeded`; `failed` and `canceled` remain distinct terminal failures.
5. Only provider-verified `succeeded` refunds change the order to `partially_refunded` or `refunded`.

Unknown states, response mismatches, malformed responses, and uncertain network failures never become successful refunds. A recent submission claim is left alone so concurrent Admin and reconciliation requests cannot both submit it. If a worker stops during the remote-call window, the claim becomes recoverable after five minutes; recovery checks Mollie before creating anything again.

Mollie's idempotency response cache is time-limited, so distributed exactly-once execution cannot be claimed indefinitely. The stable metadata lookup before creation closes the practical crash/retry window for refunds visible through Mollie's payment-refund list.

## Admin operations

The Returns & refunds screen requires the `refunds.manage` permission. Submitting creates or reuses the internal idempotent request and immediately attempts provider submission. The screen shows the internal state, provider state, and provider refund ID.

- `processing`: use **Sync provider status** when a provider ID is present.
- `processing` without a provider ID: use **Recover submission** after the five-minute claim window, or run reconciliation.
- `failed`: verify the provider rejection and remaining refundable balance before creating a new request with a new idempotency key.
- `succeeded`: no further submission is allowed.

The server uses the stored refund amount and validates it against the authoritative paid payment. Browser values are never forwarded directly to Mollie.

## Reconciliation command

Run a bounded reconciliation batch with:

```bash
php artisan commerce:reconcile --limit=50
```

`--limit` must be between 1 and 200. The command:

- fetches eligible unsettled payments from the configured provider and uses the same idempotent transition as webhook/customer return handling;
- submits or synchronizes non-terminal refunds;
- skips terminal refunds and settled/refunded orders;
- prints checked, changed, and failure counts;
- exits nonzero if any item failed, while logging only sanitized internal/provider identifiers and exception types.

The command is safe to rerun. Laravel schedules it every five minutes with a batch limit of 50 and a ten-minute `withoutOverlapping` lock. This is a conservative first cadence for webhook recovery in a small webshop. Production uses one cron invocation of `php artisan schedule:run`; see `docs/PRODUCTION.md`. A non-zero exit must be monitored and investigated.

## Provider credentials and local demo mode

Mollie operations require the environment-specific `MOLLIE_API_KEY`. Use a Mollie Test API key for staging acceptance and never commit credentials. The demo provider implements the same contract for local/testing use and remains blocked outside `local` and `testing`, even if demo mode is accidentally enabled.

## Inventory independence

A successful financial refund does not restock inventory, change fulfillment, or cancel the order. Restocking remains an explicit Return inspection operation for items marked resellable.
