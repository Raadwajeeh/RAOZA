# RAOZA Store Readiness — Phase 8

Audit date: 2026-10-15
Starting commit: `470da93177cb865a62eae6233c3e44c2b9af75ca`
Scope: local repository and GitHub CI only; no deployment, real payment, refund, email, carrier, accounting issuance, or remote database activity.

## Executive conclusion

RAOZA has a substantial and well-tested commerce foundation, but it is **not approved for launch**. The application behavior is ready for continued controlled QA. Launch remains blocked on legal identity and policy approval, VAT/accounting decisions, shipping and returns commitments, provider credentials/configuration, retention/backup policy, and a real-environment acceptance run.

Phase 8 fixed three demonstrated gaps:

- Checkout now requires an explicit Terms & Conditions acceptance, enforced by both browser and server validation.
- A closed mobile navigation is now inert, keeping hidden links out of the keyboard tab order.
- Manual fulfillment wording now makes clear that tracking exists only when entered and that the printable internal address sheet is not an official or prepaid carrier label.

It also adds a public trust/keyboard/checkout browser smoke suite, Firefox smoke coverage, and WebKit smoke coverage.

## Classification key

- **Implemented and verified** — inspected and covered by a passing automated or browser test in this phase.
- **Implemented but not fully verified** — code exists, but production/provider/physical-device evidence is unavailable.
- **Missing** — no adequate implementation or evidence exists.
- **Blocked pending owner/accountant/legal approval** — a deliberate fail-closed or incomplete business decision.

## Readiness register

### Storefront and navigation

| Finding | Classification | Evidence / action |
|---|---|---|
| Home, shop, category, collection, product, cart, account and customer-care navigation | Implemented and verified | Feature tests and Chromium E2E passed. |
| About, Contact, FAQ, Shipping, Returns, Privacy, Cookies, Terms and Size Guide routes | Implemented and verified | Every route returns successfully; footer links are asserted by the new cross-browser smoke suite. |
| Filtering, sorting, query persistence and pagination | Implemented and verified | Existing catalog feature and Playwright coverage passed. |
| Closed mobile menu keyboard behavior | Implemented and verified | Added `inert` while closed; Chromium, Firefox and WebKit smoke tests passed. |
| Final editorial/legal copy | Blocked pending owner/legal approval | Seeded copy is coherent and avoids invented facts, but is not a substitute for approved legal text. |

### Cart and checkout

| Finding | Classification | Evidence / action |
|---|---|---|
| Server-authoritative price, discount, stock, shipping, VAT and total calculation | Implemented and verified | Commerce integrity tests, money allocation tests and MariaDB concurrency suite cover these boundaries. |
| Guest checkout | Implemented and verified | Full Chromium journey creates and pays a demo guest order and verifies secure guest access. |
| Registered checkout with owned saved addresses | Implemented and verified | Ownership, snapshot immutability, default selection and E2E checkout passed. |
| Order summary before payment | Implemented and verified | Products, shipping and estimated total are visible before order creation; browser smoke asserts the summary. |
| Explicit Terms acceptance | Implemented and verified | New required checkbox plus Laravel `accepted` validation and regression tests. |
| Checkout policy links | Implemented and verified | Terms, Privacy, Shipping and Returns links are present and browser-asserted. |
| Field errors and summary alert | Implemented and verified | Inputs expose `aria-invalid`/described errors; server validation regression passes. |
| NL-only delivery scope | Implemented and verified | Server validation accepts only `NL`; UI exposes Netherlands only. |
| VAT rate and presentation for launch | Blocked pending owner/accountant approval | Current consumer UI says VAT-inclusive and uses configured 21%; owner/accountant must approve rate, treatment and wording. |
| Live shipping prices | Blocked pending owner approval | Current €4.95 QA value is configuration, not an approved launch commitment. |

### Payment safety and order communication

| Finding | Classification | Evidence / action |
|---|---|---|
| Failed/cancelled payment releases reservations exactly once and permits safe retry | Implemented and verified | Payment lifecycle, cancellation, expiry and race tests passed. |
| Payment return is not trusted as proof of payment | Implemented and verified | Provider synchronization and webhook tests fail closed. |
| Order received, payment confirmed, shipped, return, refund, invoice, guest access, verification and reset templates | Implemented and verified | Templates and outbox dispatch paths exist; queue idempotency and encrypted payload tests pass. |
| Production email delivery, links, sender identity and deliverability | Implemented but not fully verified | No real email was sent; provider/domain/DNS configuration is pending. |
| Live payment method display and provider redirect | Implemented but not fully verified | Mollie adapter is contract-tested without network calls; no live/test-account acceptance was authorized. |

### Shipping and manual fulfillment

| Finding | Classification | Evidence / action |
|---|---|---|
| Production state transitions and partial/multi-parcel fulfillment | Implemented and verified | Unit/feature/E2E tests cover printing, ready-to-ship, handover and delivery. |
| Tracking is optional and never invented | Implemented and verified | Customer presentation exposes only entered data after handover. Copy now states this explicitly. |
| Internal printable A6 address document | Implemented and verified | Private permission-protected PDF test passes. UI clarifies it is not a prepaid/official carrier label. |
| Carrier API/official label purchase | Missing | Deliberately not represented as available. Future provider stubs make no HTTP requests. |
| Carrier choice, service levels, prices and delivery commitments | Blocked pending owner/carrier approval | Required before launch and before promises are added to public copy. |

### Returns, refunds, invoices and credit notes

| Finding | Classification | Evidence / action |
|---|---|---|
| Return requests and physical return statuses | Implemented and verified | Eligibility, quantity limits, ownership, status history and inventory disposition tests pass. |
| No arbitrary return deadline by default | Implemented and verified | `CUSTOMER_RETURN_WINDOW_DAYS` defaults to null; tests assert no assumed window. Public copy states no number. |
| Return acceptance versus payment refund | Implemented and verified | UI/copy and state machines keep these separate; refund is complete only on provider confirmation. |
| Refund idempotency, amount limits and reconciliation | Implemented and verified | Provider lifecycle and integrity suites pass without sending a real refund. |
| Invoice/credit-note numbering, immutable snapshots and PDFs | Implemented and verified | Accounting document tests pass under explicit test approval flags. |
| Official accounting issuance | Blocked pending owner/accountant approval | Fail-closed flags require seller profile, accounting policy, numbering and allocation approval. No official document was issued. |
| Final return/cancellation policy | Blocked pending legal/owner approval | Eligibility logic intentionally has no invented legal deadline. Approved exceptions, costs, method and statutory wording are required. |

### Admin operations

| Finding | Classification | Evidence / action |
|---|---|---|
| Role/permission separation, staff lifecycle and last-owner protection | Implemented and verified | Authorization and Phase 5 operation tests pass. |
| Catalog, inventory ledger, orders, customers, discounts, content, fulfillment, returns and operations screens | Implemented and verified | Feature tests cover server authorization and core mutations. |
| Append-only sanitized audit trail | Implemented and verified | Tests assert permissions, immutability and secret redaction. |
| Production staffing and runbooks exercised by store team | Implemented but not fully verified | Screens and docs exist; no owner/staff acceptance session was performed. |

### Privacy, consent and support content

| Finding | Classification | Evidence / action |
|---|---|---|
| Necessary/analytics/marketing consent choices | Implemented and verified | Optional categories default off, choices persist, and analytics recording is consent-gated. |
| Cookie-preferences reopening | Implemented and verified | Footer control exists and is browser reachable. |
| Privacy retention pruning | Blocked pending legal/owner approval | Destructive pruning defaults off; every period requires explicit configuration. |
| Business identity and support details | Blocked pending owner/legal approval | No street address, KvK or VAT number was invented. `RAOZA Studio`, Amsterdam and email values require confirmation. |
| Customer support SLA | Blocked pending owner approval | Existing “two business days” statement must be confirmed operationally or changed before launch. |

### Accessibility and browser QA

| Finding | Classification | Evidence / action |
|---|---|---|
| Global focus indicator, labeled forms, alerts and skip link | Implemented and verified | Code audit plus browser/feature tests. |
| Mobile filter dialog | Implemented and verified | Native dialog, labels, keyboard focus styling and Chromium mobile test pass. |
| Mobile navigation hidden focus targets | Implemented and verified | Phase 8 fix plus Chromium/Firefox/WebKit tests. |
| Chromium full commerce/browser journey | Implemented and verified | 11 Chromium tests passed. |
| Firefox smoke | Implemented and verified | Public trust, mobile keyboard and checkout semantics tests passed. |
| WebKit smoke | Implemented and verified | Public trust and mobile keyboard tests passed. |
| WebKit state-changing checkout journey | Implemented but not fully verified | Excluded from the smoke project after an intermittent local Windows WebKit cart-action timeout; Chromium and Firefox cover the checkout assertion. |
| Full WCAG audit, assistive-technology session and physical-device test | Missing | No claim is made for screen-reader hardware/software, formal contrast certification, or physical devices. |

### Performance and operational resilience

| Finding | Classification | Evidence / action |
|---|---|---|
| Catalog eager loading, bounded filters and pagination | Implemented and verified | Query construction and regression tests show bounded, paginated results. |
| Responsive catalog images and lazy loading | Implemented and verified | Image variants/dimensions and catalog image component are present; production build passed. |
| Database transaction/locking behavior | Implemented and verified | SQLite behavior and isolated MariaDB concurrency suite cover inventory, discounts, invoices and shipments. |
| Queue/outbox and scheduler dependencies | Implemented and verified | Durable encrypted outbox, bounded retries and scheduled reconciliation tests pass. |
| PDF generation and protected access | Implemented and verified | Accounting/operational PDF validity and authorization tests pass. |
| Safe additive migrations | Implemented and verified | Fresh migrations and test seeding pass. Production migration rehearsal remains an operational prerequisite. |
| Cache/session/queue production topology | Implemented but not fully verified | Configuration and health checks exist; no deployed infrastructure was accessed. |
| Backup scripts and restore documentation | Implemented but not fully verified | MariaDB/PostgreSQL scripts/docs exist; no production backup or timed restore drill was run. |

### Security

| Finding | Classification | Evidence / action |
|---|---|---|
| Customer/admin separation and permissions | Implemented and verified | Customer credentials cannot access admin; staff cannot use customer portal. |
| IDOR protection for orders, addresses, documents and guest orders | Implemented and verified | Ownership/token tests return 404 for cross-customer access. |
| CSRF and session regeneration | Implemented and verified | State-changing request and login tests pass. |
| Authentication, verification and reset throttling | Implemented and verified | Separate customer/admin flows, verification, resets and route throttles exist. |
| Guest access tokens | Implemented and verified | Hashed, expiring, single-current-token behavior is tested. |
| Upload validation | Implemented and verified | SVG/executable content rejection tests pass. |
| Sensitive logging/auditing | Implemented and verified | Payment errors use bounded identifiers; audit tests assert password/recipient protection. |
| Public storage exposure | Implemented but not fully verified | Product media is intentionally public; document storage is private. Deployed web-server rules were not tested. |
| External tracking links | Implemented and verified | Trusted HTTPS validation/template behavior is tested; links open with `noreferrer`. |
| Confirmed Phase 8 vulnerabilities | None found | This is not a penetration-test certification. Production configuration remains unverified. |

## Public content audit

All nine required pages are present, branded consistently, linked from the footer where relevant, and reachable in browser tests. Checkout directly links Terms, Privacy, Shipping and Returns. No broken application link was found in the tested set.

The content deliberately omits unapproved address, registration/VAT numbers, return deadline and carrier promise. That omission is safe for development but blocks launch. The dedicated owner checklist lists the exact missing facts.

## Quality gates

Local results on 2026-10-15:

| Gate | Result |
|---|---|
| PHPUnit | Passed: 225 tests, 1,390 assertions |
| TypeScript | Passed |
| Vite production build | Passed |
| Build verification | Passed: 60 manifest entries, 61 files |
| Playwright | Passed: 16 tests across Chromium, Firefox and WebKit |
| Composer audit | Passed: no advisories |
| npm audit (`high`) | Passed: 0 vulnerabilities |
| MariaDB commerce | Covered by isolated GitHub job; final commit result pending at report authoring time |
| Gitleaks | Covered by GitHub `secrets` job; final commit result pending at report authoring time |
| `git diff --check` | Passed |

The final commit and GitHub Actions URLs are reported in the delivery response after all four required jobs complete.

## Launch blockers

1. Complete and sign off every item in `RAOZA_OWNER_APPROVAL_CHECKLIST.md`.
2. Replace/approve legal pages with qualified Dutch/EU legal review.
3. Approve VAT, invoice, credit-note and retention policy with the accountant.
4. Configure and test payment, email and carrier accounts without demo credentials.
5. Approve exact shipping charges, dispatch/delivery commitments, return method/costs and support SLA.
6. Rehearse backup and restoration against the chosen production database/storage.
7. Run production-like acceptance, accessibility/assistive-technology checks and representative physical-device QA.
8. Run launch preflight in the real hosting environment. This phase did not deploy or access Hostinger.
