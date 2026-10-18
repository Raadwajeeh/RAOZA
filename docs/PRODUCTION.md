# RAOZA production runbook

RAOZA is a single Laravel 12 application backed by PostgreSQL or MariaDB. The first production deployment uses database-backed queues, cache and sessions. Redis and distributed infrastructure are not required. MariaDB-specific requirements and Hostinger gaps are recorded in [MARIADB.md](MARIADB.md).

## Recommended production architecture

The provider-independent baseline is one EU/Netherlands-region application host (or equivalent managed runtime), one independently backed-up PostgreSQL service, and off-host backup storage:

```text
Internet -> DNS -> HTTPS edge/reverse proxy -> PHP-FPM -> Laravel release
                                                   |-> PostgreSQL
                                                   |-> persistent storage/app/public
Supervisor -> queue worker                         |-> Mollie HTTPS API/webhooks
systemd timer/cron -> Laravel scheduler             |-> transactional mail provider
External monitor -> health endpoints/log alerts    `-> encrypted off-host backups
```

Provider-independent requirements are TLS termination, PHP 8.3 or newer with required extensions, an atomic or retained-release deployment layout, PostgreSQL, a supervised queue worker, exactly one scheduler trigger, persistent uploaded media, off-host database/media backups, and captured application logs. Provider-specific values include host paths, proxy IPs, database CA/TLS policy, process-manager syntax, mail transport, backup destination and monitoring integrations. The owner must select the host, mail service, backup destination, monitoring destination and accountable operator; no provider is assumed or provisioned here.

For the initial traffic level, database-backed cache, sessions and queues avoid an unnecessary Redis dependency. Reassess Redis and multiple application nodes only after measured load requires them. With multiple scheduler-capable nodes, enable only one scheduler trigger unless shared locking and `onOneServer` behavior have been deliberately introduced and tested.

## Environment gate

Keep the production `.env` in the host secret store or outside the release directory. Never commit it. Required baseline:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://raoza.nl
TRUSTED_PROXIES=<comma-separated proxy IPs or CIDRs supplied by the host>
APP_KEY=base64:<unique-production-key>
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info
DB_CONNECTION=pgsql
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database-uuids
FILESYSTEM_DISK=local
PAYMENT_PROVIDER=mollie
RAOZA_DEMO_MODE=false
```

Start from `.env.production.example`, replace every placeholder in the host secret store, and keep the populated file outside source control and readable only by the application operator/runtime. `DB_SSLMODE=verify-full` is preferred for a managed remote database when its hostname and CA chain can be verified; use `require` only when the provider cannot support hostname validation. A local Unix-socket deployment needs a separately documented transport decision. Never use `disable`, `allow` or `prefer` for a remote production database.

After installing dependencies and before maintenance mode, run `php artisan production:preflight`. It validates the resolved configuration and performs only a read-only database connectivity query. It prints pass/fail labels, never configured values. For the final launch gate use `php artisan production:preflight --require-live-services`; this checks that a Live-prefixed Mollie credential is selected but does not call Mollie. A passing preflight does not prove mail delivery, webhook reachability, DNS, backups or provider activation.

Use a least-privilege database account. `TRUSTED_PROXIES` must never be `*`: leave it empty only when the HTTPS edge connects directly to PHP, otherwise list only proxy/load-balancer IPs or CIDRs supplied by the selected host. A proxy must overwrite client-supplied forwarding headers and send `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port` and `X-Forwarded-Proto`. Verify generated canonical, checkout-return and webhook URLs are HTTPS, HTTP redirects to HTTPS, and cookies carry `Secure`. Enable HSTS only at the final edge after TLS and subdomain coverage are confirmed. Do not enable Mollie Live until test-mode acceptance is complete. The demo provider is server-side blocked in production even if it is accidentally selected.

## Controlled bootstrap and staff recovery

Do not run `DatabaseSeeder`, `LocalQaSeeder` or demo seeders in production. Start with a reviewed copy of `resources/production/bootstrap-manifest.example.json`:

```bash
php artisan production:bootstrap /secure/path/catalog.json --dry-run
php artisan production:bootstrap /secure/path/catalog.json --confirm
php artisan admin:provision-owner owner@example.com --name="Owner name" --confirm
```

The manifest is slug/SKU based, portable to an empty database, and does not rely on QA product IDs. It separates reference taxonomy, product records, owner-supplied settings and inventory. Validation refuses incomplete or demo manifests; the example intentionally contains no invented variants, stock, shipping truth, legal facts or secrets. Review it outside the release tree and supply only verified owner data. Owner passwords are entered through a hidden prompt and never printed.

If normal staff mail recovery is unavailable, an operator with server console access may run `php artisan admin:reset-password staff@example.com --confirm`. It accepts staff accounts only, uses a hidden strong-password prompt, invalidates database sessions/reset tokens and writes an audit record.

## Transactional mail

Order-received, payment-confirmed, shipment and successful-refund mail is queued on the default database queue. Mail jobs dispatch after database commit and have three attempts, a 30-second job timeout and 30/120-second backoff. Verified commerce state remains authoritative; mail failure does not reverse or repeat a financial transition.

Payment-failure, intermediate fulfillment, and return-lifecycle messages are intentionally not emitted yet: failed payments can still be retried, and those Admin workflows do not have a finalized customer-communication policy. This avoids inventing messages that imply a stronger business outcome than the domain state.

Implemented customer mail is: order received, payment confirmed, shipment created, and successful refund. Admin operational notification mail is not implemented; queue/payment/backup alerts must therefore go through the selected hosting or monitoring system.

For local development, `MAIL_MAILER=log` and `local@raoza.invalid` prevent delivery. Production needs a verified sender/mailbox and a configured Laravel-supported transport. For SMTP, set `MAIL_MAILER=smtp`, host, port, username, password, scheme, timeout, from address and name. `hello@raoza.nl` may be used only after the provider verifies the domain/mailbox. Test deliverability and SPF/DKIM/DMARC before launch. RAOZA does not yet claim real email delivery.

Mail launch gate:

1. Owner selects the provider and verified From/Reply-To policy.
2. Publish provider-supplied SPF and DKIM records; publish an owner-approved DMARC policy and reporting mailbox without removing unrelated legitimate senders.
3. Verify DNS at the provider, then send queued tests to at least two unrelated mailbox providers.
4. Confirm From alignment, DKIM pass, SPF pass, DMARC pass, links, branding, mobile layout and bounce handling.
5. Force one controlled mail failure, confirm it appears in `failed_jobs`/alerts, correct it and retry only the reviewed mail job.

## Queue worker and failed jobs

Run at least one continuously supervised worker from the active release:

```bash
php artisan queue:work database --sleep=3 --tries=3 --backoff=30 --timeout=60 --max-time=3600
```

The database connection uses `retry_after=90`, safely above the worker timeout. A provider-neutral service manager must keep the worker alive and capture stdout/stderr; the systemd file is an example, not a hosting requirement. After each deploy run `php artisan queue:restart`.

Operational commands:

```bash
php artisan queue:failed
php artisan queue:retry <uuid>
php artisan queue:retry all
php artisan queue:forget <uuid>
php artisan queue:flush
```

Inspect the exception and job type before retrying. Transactional mail and other side effects are generally safe to retry after fixing the cause. Do not blindly retry an unknown financial operation; inspect provider state and run reconciliation. `queue:flush` is destructive and should only be used after failures have been reviewed and retained elsewhere if needed.

## Scheduler and reconciliation

The application schedules `commerce:reconcile --limit=50` and `commerce:expire-pending-orders --limit=100` every five minutes. Reconciliation retries uncertain payment-create outcomes with the original stable idempotency key for at most 45 minutes, safely below Mollie's documented one-hour cache. Expiry cancels only old, unpaid pending orders without provider state requiring reconciliation and releases each owned reservation exactly once. Both use ten-minute overlap locks and idempotent transactional services. The reservation window is configured with `PENDING_ORDER_RESERVATION_MINUTES` (30 minutes by default); it is an internal safety window, not a customer-facing promise. The first deployment assumes one application scheduler host.

If the retry deadline expires, provider credentials change, or provider identities conflict, the payment becomes `review_required` and inventory remains protected because remote payment absence has not been proven. Run `php artisan commerce:payment-uncertainty-report`. Resolve each attempt directly against Mollie using its order number, creation key, timestamps, and sanitized logs. Do not release inventory, retry creation, or create a replacement payment until the original remote outcome is authoritatively established.

Configure one system cron entry, not one entry per task:

```cron
* * * * * cd /path/to/raoza/current && php artisan schedule:run >> /dev/null 2>&1
```

`deployment/systemd/raoza-scheduler.service` and `.timer` are a provider-specific example that gives each invocation an observable exit status. Use either that timer or cron, never both. Alert if no successful scheduler invocation is recorded for three minutes, if either commerce command repeatedly exits nonzero, if the oldest queued job exceeds five minutes, or if any failed job is added.

## Mollie Live readiness

The code validates amount, currency and order reference against the server-side order; treats fetched provider state as authoritative; uses stable creation/refund keys; bounds automatic creation recovery to 45 minutes; makes paid transitions and inventory movements idempotent; and preserves uncertain attempts as `review_required`. Logs contain internal/provider identifiers and exception classes, not API keys or raw authorization headers.

Live activation remains blocked until all of the following are evidenced on an HTTPS staging or production-like environment:

- Mollie account/business verification is complete and the owner has approved the settlement account.
- Test and Live keys are stored as separate secrets; no key appears in Git, build logs, shell history or frontend variables.
- `https://raoza.nl/payments/webhook` is externally reachable with a valid certificate and returns without browser authentication.
- Mollie test-mode successful, failed, cancelled, expired and delayed webhook flows preserve correct order, payment, discount and inventory state.
- Customer return and webhook ordering is tested both ways; duplicate delivery remains idempotent.
- Unknown provider IDs, mismatched amounts/currencies and unsupported states fail closed and alert an operator.
- `commerce:reconcile`, `commerce:payment-uncertainty-report`, review-required resolution and refund recovery are rehearsed by the operator.
- A low-value real payment and refund plan is owner-approved for the final controlled Live test, including bookkeeping and cancellation handling.

Setting a Live key is a separate, explicitly authorized operational action. This repository phase neither performs nor verifies live webhook delivery, settlement or refunds.

Inspect and operate it with:

```bash
php artisan schedule:list
php artisan commerce:reconcile --limit=50
php artisan commerce:expire-pending-orders --limit=100
php artisan commerce:payment-uncertainty-report
```

A non-zero reconciliation exit means at least one payment/refund could not be reconciled. Review sanitized logs and provider state, fix the cause, then rerun. Alert on repeated non-zero runs.

## Deployment

Deploy a reviewed immutable commit and retain the previous release. CI or staging must pass tests, typecheck, build and migration review before production. `scripts/deploy.sh` now requires the reviewed full SHA and a checksum-verified, readable custom-format backup artifact; it still does not create the backup or decide whether a migration is safe.

1. Confirm the exact commit SHA and a clean release tree.
2. Ensure production environment/secrets and writable `storage`/`bootstrap/cache` are in place.
3. Run `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
4. Run `npm ci --no-audit --no-fund` and `npm run build`, or deploy the verified build artifact.
5. Review every pending migration for locks, table rewrites, backward compatibility and data effects. Record the last known-good release SHA.
6. Run `scripts/backup-postgres.sh`; independently retain its path/checksum and verify `pg_restore --list`. A successful dump is not a recovery proof—use the latest recorded isolated restore drill as the backup-system acceptance gate.
7. Set `DEPLOY_CONFIRM_SHA=<full SHA>` and `VERIFIED_BACKUP_FILE=<absolute dump path>`. Set `DEPLOY_REQUIRE_LIVE_SERVICES=yes` only for the separately authorized Live launch. Enter maintenance mode only immediately before the migration boundary.
8. Run forward-only `php artisan migrate --force`, then `php artisan optimize` and `php artisan storage:link --force`.
9. Run `php artisan queue:restart`, exit maintenance mode, and confirm the supervisor started a worker from the new release.
10. Verify the scheduler, health endpoints and the structured smoke plan in `docs/LAUNCH_CHECKLIST.md`. Monitor closely for at least one reservation/reconciliation window before declaring the release stable.

`scripts/deploy.sh` implements the application steps but does not fetch code, configure secrets, supervise processes, install cron or perform health requests. Never use `migrate:fresh`, `db:wipe`, `DROP DATABASE`, or an unreviewed rollback against production commerce data.

### Failure and rollback boundaries

- Before migrations: abort, keep traffic on the old release, correct the failed build/config/backup step, and start again with a fresh preflight.
- During migration: keep maintenance mode active, preserve logs and the backup, and determine whether the migration committed. Do not blindly run `migrate:rollback`.
- After a backward-compatible migration: switch the `current` release symlink back to the previous code, clear/optimize caches for that release, restart workers, restore traffic and monitor. The migrated schema remains forward.
- After a non-backward-compatible or data-damaging migration: keep traffic stopped. Code rollback and database recovery are separate decisions. Restore only to a newly provisioned database, verify it, then perform an operator-approved database cutover. Any orders accepted after the recovery point require explicit reconciliation and must not be silently discarded.
- Failed smoke test with correct data: remove traffic from the failing release, revert code if compatible, preserve evidence, and open an incident. Never compensate by editing payment or inventory rows manually.

## Health, logs and monitoring

- `/health/live` proves the Laravel process responds. It does not test dependencies.
- `/health/ready` runs only a minimal database query. It returns 503 with `database: false` on failure.
- Neither endpoint checks Mollie or mail, and neither returns exception text, credentials, SQL or paths.
- Both endpoints are registered outside the storefront/web middleware stack, so polling does not start sessions, resolve carts or record analytics.

## Retention foundation

`php artisan privacy:retention-report` is a non-destructive report by default. No retention period is assumed. Configure only owner/legal-approved `RETENTION_*_DAYS` values. Financial, order, payment, refund, inventory and audit records are excluded. Destructive pruning additionally requires both `RETENTION_PRUNING_ENABLED=true` and `--execute --confirm`; do not schedule it until the policy and restore procedure are approved.

## Catalog image delivery

Official catalog media is shipped as reviewed WebP only: one full-size image plus 960px and 640px responsive derivatives. The storefront provides `srcset`, intrinsic dimensions, lazy loading below the fold, and eager/high priority for LCP imagery. Lossless working sources belong outside the deployable repository; never generate derivatives by repeatedly recompressing the reviewed WebP files. Admin uploads continue to accept JPG, PNG and WebP without assuming static derivatives.

Production logs should go to captured stderr or rotated daily files. Financial logs use internal IDs, order numbers where useful, provider IDs and exception classes—never credentials, authorization headers, cookies, addresses or raw customer/provider payloads. Failed jobs emit sanitized job/queue context and are stored in `failed_jobs`.

Laravel's exception handler and PSR/Monolog channels are the integration points for a future error-monitoring service. No vendor SDK or DSN is committed. Until a provider is chosen, alert from hosting logs and uptime checks on HTTP 5xx, readiness failures, failed jobs/backlog, reconciliation failures, backup failure, disk capacity, database availability and TLS expiry.

Minimum alert matrix:

| Severity | Trigger examples | Required response |
|---|---|---|
| SEV-1 | Checkout unavailable, database unavailable, suspected data loss/security incident, widespread payment-state mismatch | Stop affected traffic/actions, page the operator immediately, preserve evidence, verify provider/database state, start the incident and recovery runbook |
| SEV-2 | Worker or scheduler stopped, repeated reconciliation failure, unresolved/review-required payments aging beyond 15 minutes, refund failure, backup missed | Notify operator within 15 minutes; restore processing safely and reconcile every affected order |
| SEV-3 | Elevated 5xx rate, disk over 80%, TLS under 14 days, queue oldest job over 5 minutes, isolated mail failures | Investigate during the same operating day and escalate if commerce impact appears |
| SEV-4 | Dependency updates, capacity trends, quarterly restore drill due | Track with an owner and due date |

At minimum record availability, readiness, HTTP 5xx, database connectivity, queue depth/oldest age, failed-job count, last scheduler success, command exit codes, uncertainty-report count/age, inventory reconciliation findings, refund failures, backup age/checksum/off-host-copy status, last restore-drill date/duration, disk use and TLS expiry. Logs and alerts must name the release SHA and avoid customer addresses, cookies, credentials and raw provider payloads.

## Small-shop operations checklist

Daily or alert-driven:

- Review new failed jobs, repeated reconciliation failures, payment/refund anomalies and application errors.
- Confirm the queue worker and scheduler are running.

Per deploy:

- Review/apply forward migrations, rebuild caches, restart workers, and verify both health endpoints.

Periodic:

- Confirm database and media backup success, capacity and off-site retention.
- Perform and record an isolated restore drill at least quarterly and after backup-system changes.
- Review dependency/security updates and mail/payment-provider operational notices.

See `docs/BACKUP_RESTORE.md` for the backup boundary and restore drill.
