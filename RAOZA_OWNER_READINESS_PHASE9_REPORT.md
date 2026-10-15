# RAOZA — Phase 9 Owner Readiness Report

Date: 15 October 2026  
Scope: local development and GitHub only  
Starting commit: `1fc4f031ec5c09db62778a0a594b112b1814c444`

## Outcome

RAOZA now has an Owner-only launch-readiness workspace at `/admin/readiness`. It separates technical checks, Owner decisions, accountant decisions, legal review, external-provider setup and operational verification. Every recorded decision is tied to a SHA-256 version of the reviewed content or configuration.

This work did not deploy the store, access Hostinger, change a remote database, execute a payment/refund/email/carrier operation, issue an official accounting document, fabricate business details, or activate an unapproved setting.

## Existing features reused

- The existing `AdminRole` permission model and admin middleware remain authoritative. A new `readiness.manage` permission is reachable only through the Owner wildcard, with an additional explicit Owner-role check in the controller.
- `StoreInformation` and its existing `site_content` source remain the only public business-information source. Proposed values use the adjacent `store_information_draft` record.
- `ContentPage` remains the content authority. Review drafts are attached to the same record rather than creating a second page system.
- `ShippingMethod` remains the checkout authority. Proposed changes are staged on the same record; new methods are always disabled.
- `InvoicingReadiness` supplies the accounting blockers and retains its fail-closed environment flags.
- Existing mail, queue, payment, carrier and retention configuration supplies redacted readiness signals.
- Existing append-only `AuditLog` and `AuditService` record every approval-register mutation and every staged change.

## Implemented owner workflow

The readiness dashboard shows each decision’s category, source, status, required sign-off role, dependencies, current version hash and last register update. It also shows credential-free configuration signals for Mollie, SMTP, queue processing, scheduler execution, carriers and backups.

The additive `launch_approvals` register supports:

- category and stable decision key;
- `pending`, `submitted`, `approved`, `rejected` and `superseded` states;
- non-sensitive notes and evidence references;
- submitter, approver identity and approver role;
- submission and decision timestamps; and
- the exact SHA-256 content/configuration version under review.

Owner decisions can be approved by the Owner when evidence is supplied. Accountant, legal and external-provider decisions can be submitted or rejected, but the application refuses to turn an Owner click into those external approvals. No approval action publishes content, promotes business data, applies staged shipping values, enables invoicing or changes provider configuration.

## Business information safeguards

Business identity changes now save to `store_information_draft`. The public `store_information` value is left untouched, so storefront output and existing invoice snapshots do not change.

Validation accepts incomplete drafts without encouraging invented data. Values that are supplied are format-checked, including email addresses, Dutch postal codes, eight-digit KvK numbers, Dutch VAT numbers and telephone syntax. Empty unknown fields remain empty.

Draft, approval and public state are distinct:

- draft: proposed values in `store_information_draft`;
- approved: a version-bound register state, only after the required sign-off path permits it; and
- public: the existing `store_information` value used by the storefront and future document snapshots.

There is deliberately no automatic draft-to-public action in Phase 9.

## Policy and content safeguards

Terms, Privacy, Cookies, Shipping, Returns, Contact, FAQ and Size Guide are connected to the readiness register. Edits to those controlled pages are stored in `ContentPage.draft_payload` with a SHA-256 `draft_hash`.

Saving a review draft:

- does not modify the currently public title, slug, content, SEO data or publication timestamp;
- forces the proposed status to draft even if a client submits `published`;
- supersedes submitted or approved records for the previous version; and
- requires a fresh decision against the new hash.

Terms, Privacy, Cookies, Shipping and Returns require legal sign-off. Contact, FAQ and Size Guide permit Owner approval, but approval still does not publish the draft.

## Commercial readiness

- Netherlands-only delivery scope is an explicit Owner decision.
- Shipping-method changes are staged in `pending_configuration`; existing checkout prices and promises remain live until a separate future application workflow is authorized.
- New shipping methods are created disabled even when the submitted form requests activation.
- Return method, return costs and customer-support commitments remain legal-review items.
- Customer contact values are part of the staged business-information version.

## Accounting and provider readiness

The dashboard displays all existing invoicing blockers and whether official issuance is disabled. Phase 9 does not change `INVOICE_ISSUANCE_ENABLED`, accounting-policy approval, seller-profile approval, numbering approval, payment mode or any provider setting.

Provider checks expose only safe booleans and operational descriptions. Credential values are never returned to the browser. Approval notes and evidence references reject credential-like text; API keys and passwords are not accepted as approval evidence.

Scheduler and backup readiness remain pending because application configuration cannot prove that jobs are executing or that a restore drill succeeded. Carrier counts show configuration presence only and do not claim external connectivity.

## Missing decisions and owner-supplied information

The Owner must still supply or coordinate the following without inventing values:

- legal company name, KvK number, VAT number, registered/postal address and publishable telephone/contact details;
- confirmation of Netherlands-only delivery scope;
- approved shipping methods, customer prices, dispatch times and delivery estimates;
- return method, return cost allocation and customer-support commitments;
- final content review for Contact, FAQ and Size Guide;
- legal review of Terms, Privacy, Cookies, Shipping and Returns;
- accountant approval of VAT treatment, invoice/credit-note numbering and accounting policy;
- Mollie production onboarding and credential provisioning outside this register;
- SMTP account, DNS and deliverability verification;
- production queue-worker and scheduler supervision;
- carrier account/setup verification; and
- backup retention, monitoring and a documented restore drill.

## Tests and verification

Local verification after implementation:

- PHPUnit: **230 passed, 1,416 assertions**.
- Playwright: **16 passed** across the configured Chromium journey and Chromium/Firefox/WebKit readiness smoke coverage.
- TypeScript: `tsc --noEmit` passed.
- Vite production build passed; `production:verify-build` verified 61 manifest entries and 62 files.
- Composer validation passed; Composer audit found no advisories.
- npm audit found zero vulnerabilities.

New PHPUnit coverage verifies Owner-only access, customer/staff isolation, refusal of forged accountant/legal approval, version invalidation, staged policy changes without public publication, safe shipping staging and no automatic invoicing/provider enablement. The existing browser commerce journey now also verifies that the Owner can reach the readiness dashboard and see fail-closed issuance/legal status.

## GitHub delivery

GitHub Actions results and the final delivery commit are recorded in the final handoff after the pushed workflow completes. Required jobs: `test`, `mariadb-commerce`, `e2e` and `secrets`.
