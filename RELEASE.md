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

## v1.2 — Checkout & payment hardening

- Improved checkout delivery selection and estimated-total feedback.
- Added clearer empty-delivery and validation states plus browser address autocomplete hints.
- Renamed the checkout action to “Continue to payment” to match the actual two-step flow.
- Fixed payment-retry inventory reservation logic so a cancelled order re-reserves its full quantity without mistaking reservations belonging to other orders as its own.
- Kept server-side price, discount, shipping, VAT and stock validation authoritative.

## v1.3.0 — Fulfillment & Admin Operations Review
- Hardened fulfillment admin actions with user-facing validation errors and success feedback.
- Improved production queue with busy states, variant details, order links and complete on-hold resume actions.
- Expanded admin order detail with totals, shipping address, shipment/tracking data and status history.
- Added fulfillment workflow feature coverage for paid-only transitions, production progression and hold/resume.

## v1.4.0 — Returns, refunds & restocking review
- Added admin return creation from fulfilled order detail with per-line return quantities and reason capture.
- Prevented physical return creation before fulfillment and prevented empty return requests.
- Hardened return inspection so every line must be inspected before the return can move to `inspected`.
- Kept restocking limited to inspected `resellable` items and protected the one-time restock marker.
- Reworked the admin returns screen around valid state transitions instead of showing impossible actions.
- Added return-line inspection state, restock visibility and linked order navigation.
- Added refund request UI and refund history while keeping provider confirmation authoritative: creating a refund request does not mark money as refunded.
- Hardened refund state changes so failed/cancelled refunds cannot later be marked succeeded accidentally.
- Added service-level coverage for fulfilled-only and non-empty physical returns.

## v1.5.0 — Catalog, variants & inventory administration
- Reworked Products admin into a catalog-management entry point with search and dedicated product detail management.
- Added product detail editing for status, slug, description, base price, taxonomy and SEO metadata.
- Added product option/value management for attributes such as Size and Color, including optional swatch metadata.
- Added safe variant creation with SKU uniqueness, exact one-value-per-option validation, duplicate-combination prevention and optional price overrides.
- Variant creation now initializes inventory atomically and records opening stock as an inventory movement.
- Added inline variant status/SKU/price maintenance with current on-hand, reserved and available inventory visibility.
- Added product-image registry management for public/CDN paths while intentionally deferring binary upload/storage until production media storage is selected.
- Added catalog-management feature coverage for opening inventory and complete option combinations.

## v1.7.0 — Taxonomy, discounts, content & customers admin
- Added editable Categories and Collections with hierarchy/status/position controls and collection SEO/indexing fields.
- Added discount editing, validity/limit controls, paid-use visibility and case-insensitive duplicate-code protection.
- Fixed Content admin audit logging to use the project AuditService contract correctly.
- Added controlled-page editing for publication state, slug, SEO metadata, canonical/OG metadata, indexing and structured content.
- Improved Customers admin search and changed lifetime value to paid-order value rather than counting unpaid/cancelled order totals.
- Kept guest checkout and data-minimisation boundaries explicit; the customer view remains operational rather than a marketing CRM.


## v1.8.0 — Circular editorial product detail
- Adopted a circular editorial product-gallery system for the product detail page while retaining the established RAOZA identity system.
- Replaced the conventional rectangular gallery with one large circular hero image and orbiting circular gallery thumbnails.
- Added responsive desktop/mobile compositions rather than shrinking the desktop layout onto small screens.
- Preserved server-authoritative variant, stock, quantity and add-to-cart behaviour.
- Improved variant switching so a newly selected option can clear incompatible prior selections instead of trapping the shopper in an impossible combination.
- Added editorial product metadata and disclosure sections without inventing production-specific product claims.

## v1.9.0 — Circular cart & checkout
- Extended the circular editorial design language into the bag and checkout without changing the established RAOZA identity system.
- Reworked bag line items around compact circular product portraits and editorial item numbering while preserving quantity, stock and removal behaviour.
- Rebuilt the order summary as a quieter editorial panel with circular count motifs and clearer checkout hierarchy.
- Reorganized checkout into numbered editorial sections for contact, address, delivery and billing.
- Restyled delivery selection, form fields, totals and discount entry while preserving server-authoritative checkout calculations and validation.
- Kept mobile layouts intentionally linear and transactional rather than forcing the desktop orbit composition into small screens.

## v2.0.0 — Payment states & order confirmation
- Rebuilt the customer-facing order confirmation around the circular editorial system established in product, bag and checkout.
- Added a four-step order-progress presentation driven only by real payment and fulfillment state; no unsupported delivery promises are shown.
- Separated order contents, totals and operational statuses into a clearer post-checkout hierarchy.
- Added explicit failed/cancelled payment recovery messaging and retained the existing retry-payment flow.
- Redesigned the local payment simulator to match the RAOZA presentation while keeping it clearly labelled as development-only.
- Preserved server-authoritative payment verification, inventory reservation and order-state behaviour.


## v2.1.0 — Storefront system polish
- Reviewed the customer storefront as one connected journey rather than as isolated pages.
- Added subtle sticky-header elevation only after scroll while retaining the established RAOZA navigation structure.
- Refined product-card media boundaries and hover behaviour without extending the circular motif into the shopping grid.
- Added pagination relationship hints and live-region semantics to the catalog result area.
- Tightened small-screen button/container behaviour and reduced-motion handling.
- Preserved the circular editorial system for product, bag, checkout and order experience while keeping catalog browsing rectangular and comparison-friendly.
- No commerce-domain, pricing, payment, inventory or order-state logic was changed in this pass.
