# RAOZA Launch Checklist

## Infrastructure
- [ ] Production host and PostgreSQL provisioned
- [ ] `raoza.nl` DNS points to production
- [ ] TLS/HTTPS active and renewal monitored
- [ ] Staging and Production credentials are separate
- [ ] Persistent media storage configured and backed up

## Application
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Composer install succeeds with production flags
- [ ] `npm ci`, typecheck and build succeed
- [ ] Full `php artisan test` suite passes against supported test DB
- [ ] Production migrations reviewed and applied successfully
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

## Legal / privacy
- [ ] Current Dutch/EU terms, privacy, cookie and returns/withdrawal content reviewed for the real business
- [ ] Printed/customised-product return rules reflect the actual product model and current law
- [ ] Consent banner/categories verified before analytics/marketing is enabled
- [ ] No fabricated reviews, scarcity, sustainability or shipping claims

## Operations
- [ ] Admin OWNER account created securely
- [ ] Default/test staff accounts absent
- [ ] Backup job scheduled and off-host copy verified
- [ ] Persistent `storage/app/public` media backup scheduled and verified
- [ ] Restore drill completed in an isolated environment
- [ ] Error/uptime/queue/backup alerts reach an operator
- [ ] Rollback procedure tested

## Final launch gate
Do not call the shop production-ready until every applicable unchecked item above has an owner and resolution. v0.14 supplies deployment foundations; infrastructure credentials, carrier selection, legal review and live-provider acceptance remain real-world launch dependencies.
