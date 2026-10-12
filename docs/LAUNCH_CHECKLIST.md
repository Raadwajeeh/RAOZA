# RAOZA Launch Checklist

Every item needs evidence, an accountable owner and a date. Example/seed content is not verified production content.

## Infrastructure
- [ ] Production host and the selected PostgreSQL or MariaDB backend provisioned
- [ ] `raoza.nl` DNS points to production
- [ ] Owner decides whether `www.raoza.nl` exists; if it does, DNS and the certificate cover it before the HTTPS redirect is enabled
- [ ] TLS/HTTPS active and renewal monitored
- [ ] Staging and Production credentials are separate
- [ ] Persistent media storage configured and backed up
- [ ] Hosting region, data-processing terms, operator access and incident contact approved
- [ ] Database transport uses verified TLS or an owner-approved documented local transport
- [ ] Previous release retention and atomic code-switch mechanism rehearsed

## Application
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Composer install succeeds with production flags
- [ ] `npm ci`, typecheck and build succeed
- [ ] Full `php artisan test` suite passes against supported test DB
- [ ] Production migrations reviewed and applied successfully
- [ ] `production:preflight` passes; final authorized launch also passes `--require-live-services`
- [ ] `/health/live` and `/health/ready` return healthy responses
- [ ] Queue worker supervised and failed-job monitoring enabled
- [ ] One scheduler cron invokes `php artisan schedule:run` every minute

## Commerce
- [ ] Real RAOZA products, variants, prices, SKUs and stock verified
- [ ] Mollie test flow passes before live key is enabled
- [ ] Mollie live webhook URL configured and verified
- [ ] Successful, failed, cancelled and expired payment flows smoke-tested
- [ ] Stock commit/release verified with real payment states
- [ ] Shipping methods/prices and actual carrier process approved
- [ ] Order/dispatch/refund emails delivered through production mail provider
- [ ] `commerce:payment-uncertainty-report` review workflow assigned to an operator
- [ ] Mark Tee discrepancy resolved: current campaign imagery/alt text is Deep Burgundy, while only owner-approved manifest variants may be sellable; imagery, selected colour label, SKU and physical blank must agree before publication

## Legal / privacy
- [ ] Current Dutch/EU terms, privacy, cookie and returns/withdrawal content reviewed for the real business
- [ ] Printed/customised-product return rules reflect the actual product model and current law
- [ ] Consent banner/categories verified before analytics/marketing is enabled
- [ ] No fabricated reviews, scarcity, sustainability or shipping claims

## Owner input required

- [ ] Registered legal business name, KVK number, BTW/VAT number and business address verified
- [ ] Customer-service email/phone, return address and escalation contact verified
- [ ] Shipping methods, prices, dispatch times, delivery promises and carrier process approved
- [ ] VAT treatment and invoice/bookkeeping process approved by the owner/accountant
- [ ] Every product's blank-garment composition, printing method, care, sizing, fit, SKU, price, sellable colour and opening inventory verified
- [ ] GDPR retention periods, processor agreements and final privacy/cookie/legal/returns policies approved
- [ ] Confirm whether any printed/customised item qualifies for a withdrawal exception; do not infer this from the production method
- [ ] Owner-approved production manifest is stored outside the release and passes `production:bootstrap --dry-run`

## Operations
- [ ] Admin OWNER account created securely
- [ ] Default/test staff accounts absent
- [ ] Backup job scheduled and off-host copy verified
- [ ] Persistent `storage/app/public` media backup scheduled and verified
- [ ] Restore drill completed in an isolated environment
- [ ] Error/uptime/queue/backup alerts reach an operator
- [ ] Rollback procedure tested
- [ ] Named on-call operator receives SEV-1/SEV-2 alerts and can access Mollie, mail, hosting, database and backups
- [ ] Queue oldest-age/failed-job and scheduler-last-success alerts tested
- [ ] Backup freshness, off-host copy, disk and TLS-expiry alerts tested

## Structured smoke test

Automated before release: `php artisan test`, TypeScript typecheck, production asset build, strict Composer validation/audits, route listing, schedule listing, PHP syntax checks and `git diff --check`. PostgreSQL concurrency tests remain opt-in and may run only against a separately isolated disposable server/database.

Manual staging, with non-live payments and a non-delivering or approved test mail route:

- [ ] Home, collection and product pages render; SEO title/canonical/robots/sitemap basics are correct
- [ ] Product variant/price/availability match the reviewed manifest, including the Mark Tee colour decision
- [ ] Cart add/update/remove and mobile layouts work at supported widths
- [ ] Checkout validates address, shipping, VAT-inclusive totals and discount limits
- [ ] Mollie test payment initiation, return and webhook flows cover success/failure/cancel/expiry and duplicate delivery
- [ ] Reservation becomes committed only after paid state; terminal failure/expiry releases it once
- [ ] Order-received and payment-confirmed mail queue and render correctly
- [ ] Admin login/RBAC, order view, refund recovery, return inspection and fulfillment/shipment flow work
- [ ] Shipment and successful-refund mail queue and render correctly
- [ ] `/health/live` and `/health/ready` expose no secrets and monitoring handles a simulated readiness failure
- [ ] Consent choices persist correctly and disabled analytics/marketing do not load

Final controlled production acceptance, only after explicit owner authorization:

- [ ] DNS/TLS/redirects, secure cookie and proxy behavior verified externally
- [ ] Queue, scheduler, logs and alerts observed from the production release
- [ ] One owner-approved low-value Live payment/refund is reconciled against Mollie, inventory, email and bookkeeping
- [ ] Monitoring remains clean through at least one reservation/reconciliation window

## Final launch gate
Do not call the shop production-ready until every applicable unchecked item above has evidence and resolution. Infrastructure credentials, verified business/catalog facts, carrier selection, legal review, restore evidence, mail delivery and Live-provider acceptance remain real-world launch dependencies.
