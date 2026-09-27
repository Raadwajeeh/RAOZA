# RAOZA Architecture Baseline — v0.1

## Shape
Modular monolith: one Laravel application, React/Inertia UI, PostgreSQL database.

## Planned domains
Catalog → Inventory → Cart/Checkout → Orders → Payments → Fulfillment/Shipping → Returns/Refunds.

## Cross-cutting layers
Authentication, authorization, validation, privacy/consent, audit, logging, queues, caching, SEO, analytics, accessibility, performance, backups and monitoring.

## Invariants
1. Server is source of truth for money, stock, discounts, shipping totals and payment state.
2. Money uses integer minor units in commerce domains.
3. Inventory is variant-level and changes are auditable.
4. Orders retain immutable purchase snapshots.
5. Payment success requires server-side provider verification.
6. Webhook and refund processing must be idempotent.
7. Guest checkout remains supported; customer account is optional.
8. Provider integrations are behind application boundaries.
9. Admin UI cannot bypass domain rules.
10. No fake reviews, statistics, scarcity or product/business claims.


## v0.2 Catalog domain
Catalog now owns Products, dynamic Options/Values, Variants, Categories and Collections. Product and variant prices are stored as integer EUR minor units. Inventory remains a separate domain and is intentionally deferred to v0.3. Variant combinations use a canonical option-value signature plus a database unique constraint to prevent duplicate combinations for one product. Categories describe product taxonomy; Collections remain independent editorial/marketing groupings.

## v0.3 Media + Inventory
- Product images belong to a product and may optionally target a specific variant.
- Inventory is variant-level: exactly one inventory row per purchasable variant.
- Available quantity is derived as `quantity_on_hand - quantity_reserved` and is never stored separately.
- Inventory changes are performed through `InventoryService` using database transactions and row locking.
- Inventory movements provide the audit trail for on-hand adjustments. Reservations are state, not stock movements; checkout/order v0.6 will add reservation/finalization semantics without duplicating stock ownership.
- Inventory is the sole stock source of truth. Catalog, storefront and future admin/order domains must not write quantities directly.

## v0.4 Storefront boundary
`StorefrontController` is a read-oriented presentation boundary over Catalog + Inventory. It only exposes active/published products and active variants. Prices and availability are derived server-side; React receives presentation DTOs rather than owning commerce truth. Cart/checkout mutations remain outside this release.

## Commerce / Cart — v0.5

`CartService` is the application boundary for cart mutation and summary calculation. Guest carts persist through a random UUID cookie; Laravel's web middleware encrypts/signs cookies. Cart rows store only variant identity and quantity, not trusted price snapshots. Every add/update revalidates publication state, variant state and live available inventory under a database transaction/row lock. Merely adding to a cart does not reserve inventory. Checkout/order creation will establish the reservation boundary in v0.6.

## v0.6 — Checkout & Orders

Checkout now creates an immutable commercial boundary from the live cart. Laravel revalidates sellability, locks inventory rows in deterministic variant order, recalculates prices, snapshots product/variant/address/money data, reserves stock, creates status history, and converts the source cart in one database transaction.

Order, payment, and fulfillment states are separate. v0.6 intentionally keeps discount, shipping, and tax amounts at zero until their real engines/configuration exist; the fields and server-authoritative calculation boundary are already present. No payment credentials are collected in v0.6.

## v0.7 Payments
- Payments are attempts (`orders 1:N payments`), allowing safe retry without rewriting order history.
- `PaymentProvider` isolates provider-specific API behavior; Mollie is the first adapter.
- Browser redirects are never proof of payment. Both return and webhook paths fetch the payment server-to-server and verify amount, currency, provider payment id, and order metadata.
- Provider events are idempotent through a unique deterministic `payment_events.event_key`.
- A verified paid transition atomically converts reserved stock into an on-hand decrement and records inventory movements.
- Failed/cancelled/expired latest attempts release reservations. A later retry re-locks and re-reserves stock only if it is still available.
- A stale terminal webhook from an older attempt cannot release inventory reserved for a newer attempt.
- Webhook CSRF is excluded only for the dedicated provider endpoint; the endpoint does not trust webhook payload status and fetches authoritative provider state.
- Mollie credentials are environment secrets, never admin-editable application data.


## Fulfillment boundary (v0.8)
Paid orders enter PROCESSING, then PRINTING, READY_TO_SHIP and FULFILLED. Shipping never decrements inventory; inventory was committed at verified payment. Shipments are 1:N per order and shipment items preserve split-shipment capability. Carrier integration is behind ShippingProvider.

## Returns boundary (v0.9)
`ReturnRequest` describes the physical merchandise workflow; `Refund` describes money movement. They are related but intentionally independent. Receiving an item never mutates sellable inventory by itself. Inspection is the stock boundary: only `resellable` items are returned to `quantity_on_hand`, with an immutable inventory movement and a `restocked_at` guard. Refund totals are constrained against the verified paid amount and already succeeded/pending refunds. Provider-side refund execution is intentionally a later adapter concern; v0.9 never reports money as refunded merely because an admin requested it.


## v0.10 Admin boundary
Admin is an operational interface over the same domain services used by commerce. Staff roles are OWNER, ADMIN, ORDER_MANAGER, FULFILLMENT and SUPPORT. Backend permissions remain authoritative. Inventory adjustments are service-backed and audited. Payments, shipments, returns and refunds remain contextual to orders. Discounts are persisted as rules but checkout application is deferred.

## v0.11 — Content, SEO, Email, Consent & Analytics
- Controlled CMS via `content_pages` and structured JSON content; no arbitrary page-builder HTML.
- SEO metadata supports title, description, canonical, OG image and indexability. Sitemap only includes public indexable entities.
- `SeoService` is the backend source for canonical metadata and structured data.
- Transactional email is queue-first; mail transport failure must not invalidate a paid order.
- Consent categories: Necessary, Analytics, Marketing. Optional categories default off.
- Analytics provider remains unselected. IDs are environment configuration only.
- `purchase` analytics is designed around verified paid orders, not a success-page visit.

## Storefront presentation boundary — v0.12
The storefront now uses a centralized RAOZA presentation system in `resources/css/app.css` and shared React layout/components. The presentation layer does not calculate authoritative prices, stock, order totals or payment state. Product imagery continues to come from Catalog media; neutral branded placeholders are used when media is absent rather than shipping fake product photography. Responsive behavior is mobile-first, with keyboard focus and reduced-motion support included at the base layer.

## v0.13 hardening boundary
Security/integration review now enforces explicit Admin permissions on read and mutation routes, staff-only Admin authentication, baseline response headers, and return/restock consistency. Provider webhooks remain the only CSRF exception and must continue to be verified server-to-server. Full automated integration tests are a release gate once Composer dependencies and PostgreSQL are available in CI/staging.

## v1.0 Launch Candidate — Production boundary
Production is treated as an operational boundary, not an application feature. CI must prove dependencies, TypeScript, frontend build, migrations and automated tests before deployment. Runtime health distinguishes liveness from database readiness. Queue workers are supervised independently. PostgreSQL backups include checksums and require off-host retention plus restore drills; uploaded media requires its own persistent backup strategy. Secrets and infrastructure-specific credentials remain outside Git.
