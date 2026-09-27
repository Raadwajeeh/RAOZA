# RAOZA Security Baseline — v0.13

This document records the application-level security assumptions that must remain true through launch.

## Trust boundaries
- Browser-submitted prices, totals, stock, discounts, payment states and fulfillment states are never authoritative.
- Checkout snapshots money and product data from server-side catalog/inventory state.
- A payment redirect is not proof of payment. Provider state is fetched server-to-server before an order becomes paid.
- Mollie webhook processing is CSRF-exempt by necessity, but the supplied payment id is used only to locate a local payment; provider state is fetched and amount/currency/order metadata are verified before mutation.

## Admin
- `/admin/login` accepts staff accounts only.
- Admin routes require both authenticated staff membership and the route-specific permission.
- Sensitive inventory/refund/shipment mutations are additionally rate limited.
- Legacy `is_admin` remains a temporary compatibility bridge; migrate legacy accounts to an explicit role before removing it.

## Sessions / CSRF / cookies
- Laravel web CSRF protection remains enabled everywhere except the payment-provider webhook.
- Sessions are regenerated after staff login and invalidated on logout.
- The guest cart cookie is HttpOnly + SameSite=Lax and follows `SESSION_SECURE_COOKIE` so production can require HTTPS without breaking local HTTP development.

## Response hardening
Baseline responses set nosniff, clickjacking protection, strict referrer policy, restricted browser permissions, COOP and CORP headers.

A strict Content-Security-Policy is intentionally not guessed in v0.13. Define and test the final CSP after production asset/analytics/payment domains are known; do not ship a copied CSP that breaks Inertia/Vite or silently permits broad origins.

## Inventory / money invariants
- Money remains integer minor units.
- Inventory rows are locked for reservation/commit/release paths.
- Paid-order stock is committed once at the order state boundary.
- Refund requests cannot exceed the remaining refundable amount.
- A return item already restocked as resellable cannot later be reclassified as non-resellable without an explicit inventory reversal.

## Production requirements
Before launch: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, real secrets outside Git, production PostgreSQL credentials with least privilege, queue workers, backups + restore test, error monitoring, and provider keys in live mode only after staging verification.
