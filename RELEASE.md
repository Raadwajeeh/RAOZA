# RAOZA release status — 1.0.0-rc2-local-demo

## What this candidate is for

This candidate is intentionally **fully populated for local review**. It is meant to expose the storefront, variants, stock states, cart, checkout calculations, discount validation, shipping selection, payment outcomes, admin, fulfillment and content before real business data replaces the examples.

## Local-demo functionality now present

- Seeded T-shirts and hoodies with Size/Color variants.
- In-stock, low-stock and sold-out examples.
- Categories and collections.
- Branded SVG demo artwork clearly marked as placeholders.
- Guest cart and checkout.
- Two demo shipping methods.
- VAT-inclusive demo calculation with 21% configurable basis-points default.
- Discount redemption and usage limits (`WELCOME10`, `RAOZA5`).
- Order snapshots and stock reservation.
- Local payment simulator with success/failure/cancellation.
- Paid-order stock commit and fulfillment start.
- Admin owner account and operational views.
- Structured demo content/legal-information pages.
- Consent, SEO, email queue foundation, health/deployment tooling.

## Production blockers — must be replaced/approved

1. Real product names/descriptions/SKUs/images/prices/stock.
2. Final Netherlands VAT/tax treatment confirmed for RAOZA's actual business situation.
3. Real carrier/provider and shipping rates/conditions/delivery promises.
4. Mollie live/test credentials and end-to-end Mollie verification.
5. Final returns/withdrawal policy and treatment of genuinely personalised goods.
6. Final Terms, Privacy, Cookie, company identity/contact/legal information.
7. Production SMTP/provider and sender-domain authentication.
8. Analytics IDs/providers only after final consent configuration.
9. Production domain/DNS/TLS/hosting/database/backups/monitoring.
10. Dependency lockfiles generated from an online development environment.
11. Full PHPUnit, TypeScript and production Vite build green in CI/staging.
12. Replace/remove the demo admin credentials and ensure `RAOZA_DEMO_MODE=false` + `PAYMENT_PROVIDER=mollie` in production.

## Release rule

Do not call the deployment `1.0.0 production` until every blocker above is checked against the real store configuration and the automated release gates pass.
