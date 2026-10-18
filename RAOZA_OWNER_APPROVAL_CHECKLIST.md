# RAOZA Owner Approval Checklist

Status: **open — launch blocked**
Prepared: 2026-10-15

This register is intentionally blank where the owner, accountant or legal adviser has not supplied an approved answer. Do not convert an unchecked item into a default configuration or public promise.

## 1. Legal business identity

- [ ] Approve the exact legal seller name.
- [ ] Supply and approve the complete business/visiting address that must appear publicly and on accounting documents.
- [ ] Supply and approve the KvK registration number.
- [ ] Supply and approve the VAT identification number, if applicable.
- [ ] Confirm whether “RAOZA Studio” and “Amsterdam” are accurate or must be replaced.
- [ ] Confirm the legal contact email and telephone number, including whether a phone number must be public.
- [ ] Confirm the governing-law, complaint and dispute-resolution wording with legal counsel.

Required output: one signed-off seller profile, with source/date/approver, for store settings and invoice snapshots.

## 2. VAT and accounting

- [ ] Accountant confirms whether prices are VAT-inclusive and whether 21% is correct for every sold item/shipping treatment.
- [ ] Accountant approves VAT extraction/allocation and rounding behavior.
- [ ] Accountant approves invoice issuance timing (for example, confirmed payment) and credit-note timing.
- [ ] Accountant approves required invoice/credit-note fields and seller/buyer address rules.
- [ ] Accountant approves the credit allocation method for partial refunds.
- [ ] Confirm whether consumer invoices are automatic, on request, or both.

Required configuration after approval: `INVOICE_ACCOUNTING_POLICY_APPROVED`, `INVOICE_SELLER_PROFILE_APPROVED`, `INVOICE_ISSUANCE_ENABLED`, and the reviewed production manifest. Issuance must remain disabled beforehand.

## 3. Invoice numbering

- [ ] Approve numbering scope (global, per year, or another accountant-approved scope).
- [ ] Approve invoice prefix.
- [ ] Approve credit-note prefix.
- [ ] Approve number padding.
- [ ] Approve year-boundary behavior and the first production sequence values.
- [ ] Approve the recovery process for failed PDF/storage operations without reusing numbers.

Required configuration after approval: `INVOICE_NUMBERING_APPROVED`, `INVOICE_NUMBERING_SCOPE`, `INVOICE_NUMBER_PREFIX`, `CREDIT_NOTE_NUMBER_PREFIX`, and `INVOICE_NUMBER_PADDING`.

## 4. Returns, cancellation and refunds

- [ ] Legal adviser approves the final Dutch/EU withdrawal, return, cancellation and conformity wording.
- [ ] Decide whether a return-request window is configured; provide the legally reviewed duration and its start event if yes.
- [ ] Define exclusions/exceptions, especially personalized or produced-to-order items, without assuming they apply.
- [ ] Approve required item condition and acceptable fit-check handling.
- [ ] Decide who pays return transport for change-of-mind, damaged and incorrect items.
- [ ] Approve the exact return address and whether it may be published or disclosed only in instructions.
- [ ] Approve the operational authorization/instructions flow; no prepaid label is currently generated.
- [ ] Approve refund scope for item price, outbound delivery and return costs by scenario.
- [ ] Approve customer-facing processing-time wording without promising a provider/bank time the store cannot control.

Required configuration after approval: optional `CUSTOMER_RETURN_WINDOW_DAYS`, approved content-page copy and staff runbook. Null currently means no arbitrary application deadline.

## 5. Shipping prices and commitments

- [ ] Approve Netherlands-only scope or formally authorize a scope change.
- [ ] Approve each shipping method name and exact consumer price.
- [ ] Approve tax treatment for shipping.
- [ ] Select carrier(s), account(s) and service code(s).
- [ ] Approve handling/production estimate and dispatch cut-off wording.
- [ ] Approve delivery-estimate wording and exceptions; avoid guaranteed arrival language unless contractually supported.
- [ ] Decide when tracking is available and who enters/verifies it.
- [ ] Decide whether complimentary shipping exists and its eligibility rules.
- [ ] Approve lost, delayed, refused and address-error procedures.

Current caution: the €4.95 local value is not approved launch pricing. Manual fulfillment and the internal address sheet do not purchase postage or create an official carrier label.

## 6. Support and customer communication

- [ ] Approve support and contact email addresses.
- [ ] Approve or remove the current two-business-day response target.
- [ ] Set support operating days/hours and holiday coverage.
- [ ] Assign owners for payment, fulfillment, return, privacy and security escalations.
- [ ] Approve every transactional email subject/body/footer and its store links.
- [ ] Approve sender name, reply-to, legal footer and language strategy.

Required output: support runbook, escalation roster and approved template set.

## 7. Privacy and cookies

- [ ] Legal adviser approves controller identity and privacy contact details.
- [ ] Approve purposes, lawful bases, data categories, recipients/processors and international-transfer wording.
- [ ] Approve data-subject request intake, identity verification, response and deletion procedures.
- [ ] Inventory every production cookie/storage key and classify it as necessary, analytics or marketing.
- [ ] Approve analytics/marketing vendors before enabling either category.
- [ ] Approve consent policy version and evidence-retention approach.
- [ ] Approve final Privacy Policy and Cookie Policy publication dates/versioning.

Current behavior: optional analytics and marketing default off. No vendor may be named or enabled until accurately configured and approved.

## 8. Terms and content pages

- [ ] Legal adviser approves Terms & Conditions.
- [ ] Owner approves About, Contact, FAQ, Shipping, Returns and Size Guide facts.
- [ ] Supply product-specific composition, origin, fit, measurements and care information where required.
- [ ] Confirm product photography/copy rights and trademark permissions.
- [ ] Confirm the public brand description and Netherlands market statement.
- [ ] Approve checkout Terms checkbox wording and evidence requirements.

Required output: versioned approved copy with approver and effective date. Phase 8’s text is readiness-safe draft content, not legal approval.

## 9. Payment provider

- [ ] Approve Mollie (or formally select another supported provider).
- [ ] Complete business/account verification and contract review.
- [ ] Configure production API credentials outside source control.
- [ ] Register and verify HTTPS return/webhook URLs.
- [ ] Approve enabled payment methods, currencies and fraud/risk settings.
- [ ] Perform provider test-mode acceptance for paid, failed, cancelled, expired, duplicate webhook and delayed webhook outcomes.
- [ ] Approve reconciliation ownership and alert response.

Current behavior: demo payment is blocked in production and no real payment/refund was triggered.

## 10. Email provider

- [ ] Select and contract the provider.
- [ ] Configure production credentials outside source control.
- [ ] Configure and verify sending domain, SPF, DKIM and DMARC.
- [ ] Approve from/reply-to/bounce addresses.
- [ ] Confirm queue workers, retry policy, failed-job monitoring and bounce/complaint handling.
- [ ] Run inbox/link tests for supported mail clients and transactional scenarios.

Current behavior: tests use array/fake delivery; no deliverability claim is made.

## 11. Carrier and fulfillment configuration

- [ ] Select carrier account(s) and service contracts.
- [ ] Approve tracking URL templates/domains.
- [ ] Decide whether labels are bought outside RAOZA or require a future integration.
- [ ] Approve handover evidence and delivered-status procedure.
- [ ] Approve parcel, packaging, customs (if scope later expands) and dangerous-goods rules.
- [ ] Train staff that creating a draft/tracking number is not handover or delivery.

Current behavior: carrier provider adapters are non-network stubs. The generated A6 document is only an internal address sheet.

## 12. Retention, backups and recovery

- [ ] Legal/accounting approve retention periods for orders, invoices, credit notes, refunds and audit records.
- [ ] Approve periods for analytics events, sessions, consent records, failed jobs and password-reset tokens.
- [ ] Approve deletion/anonymization exceptions, legal holds and customer-request handling.
- [ ] Select database, document and media backup locations and encryption/access controls.
- [ ] Approve backup frequency, retention generations, off-site copy and monitoring.
- [ ] Define RPO and RTO.
- [ ] Complete a timed restore drill into an isolated environment and retain evidence.
- [ ] Approve recovery ownership and incident communication.

Required configuration after approval: explicit `RETENTION_*_DAYS` values and `RETENTION_PRUNING_ENABLED`; pruning remains disabled until then.

## 13. Security, monitoring and operations sign-off

- [ ] Provision named owner/staff accounts; remove/disable demo credentials and review least privilege.
- [ ] Configure production secrets, HTTPS, trusted proxies, secure cookies and restrictive filesystem permissions.
- [ ] Configure application/error, queue, scheduler, payment reconciliation, backup and capacity monitoring.
- [ ] Define alert destinations and on-call/response ownership.
- [ ] Approve vulnerability update cadence and incident-response process.
- [ ] Perform a production-like authorization, storage and web-server exposure review.
- [ ] Run accessibility review with keyboard, screen reader and representative physical devices.
- [ ] Run production preflight and launch acceptance without enabling real transactions until explicit go-live approval.

## Final authorization

- [ ] Owner confirms all commercial facts and operational commitments.
- [ ] Accountant confirms VAT, numbering, document and retention decisions.
- [ ] Legal adviser confirms identity, terms, privacy/cookies, returns and required consumer information.
- [ ] Technical owner confirms provider configuration, monitoring, backups, restore evidence and security controls.
- [ ] Named approver records a separate go-live decision.

No checked items or approval signatures were fabricated during Phase 8.
