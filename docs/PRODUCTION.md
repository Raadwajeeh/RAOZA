# RAOZA production runbook

RAOZA is a single Laravel 12 application backed by PostgreSQL. The first production deployment uses database-backed queues, cache and sessions. Redis and distributed infrastructure are not required.

## Environment gate

Keep the production `.env` in the host secret store or outside the release directory. Never commit it. Required baseline:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://raoza.nl
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

Use a least-privilege database account. Configure trusted proxy/HTTPS forwarding according to the selected host, terminate TLS correctly, and verify that Laravel sees requests as HTTPS. Do not enable Mollie Live until test-mode acceptance is complete. The demo provider is server-side blocked in production even if it is accidentally selected.

## Transactional mail

Order-received, payment-confirmed, shipment and successful-refund mail is queued on the default database queue. Mail jobs dispatch after database commit and have three attempts, a 30-second job timeout and 30/120-second backoff. Verified commerce state remains authoritative; mail failure does not reverse or repeat a financial transition.

Payment-failure, intermediate fulfillment, and return-lifecycle messages are intentionally not emitted yet: failed payments can still be retried, and those Admin workflows do not have a finalized customer-communication policy. This avoids inventing messages that imply a stronger business outcome than the domain state.

For local development, `MAIL_MAILER=log` and `local@raoza.invalid` prevent delivery. Production needs a verified sender/mailbox and a configured Laravel-supported transport. For SMTP, set `MAIL_MAILER=smtp`, host, port, username, password, scheme, timeout, from address and name. `hello@raoza.nl` may be used only after the provider verifies the domain/mailbox. Test deliverability and SPF/DKIM/DMARC before launch. RAOZA does not yet claim real email delivery.

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

The application schedules `commerce:reconcile --limit=50` every five minutes. This gives a small webshop prompt webhook recovery without excessive Mollie traffic. A ten-minute `withoutOverlapping` lock prevents uncontrolled overlap; the underlying service remains idempotent. The first deployment assumes one application scheduler host.

Configure one system cron entry, not one entry per task:

```cron
* * * * * cd /path/to/raoza/current && php artisan schedule:run >> /dev/null 2>&1
```

Inspect and operate it with:

```bash
php artisan schedule:list
php artisan commerce:reconcile --limit=50
```

A non-zero reconciliation exit means at least one payment/refund could not be reconciled. Review sanitized logs and provider state, fix the cause, then rerun. Alert on repeated non-zero runs.

## Deployment

Deploy a reviewed commit and retain the previous release. CI or staging must pass tests, typecheck, build and migration review before production.

1. Confirm the exact commit SHA and a clean release tree.
2. Ensure production environment/secrets and writable `storage`/`bootstrap/cache` are in place.
3. Run `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
4. Run `npm ci --no-audit --no-fund` and `npm run build`, or deploy the verified build artifact.
5. Take a verified backup/checkpoint before sensitive schema/data migrations.
6. Enter maintenance mode, then run forward-only `php artisan migrate --force`.
7. Run `php artisan optimize` and `php artisan storage:link --force`.
8. Run `php artisan queue:restart`, exit maintenance mode, and confirm the supervisor started the new worker.
9. Verify `php artisan schedule:list`, `/health/live`, `/health/ready`, storefront checkout entry and Admin login.

`scripts/deploy.sh` implements the application steps but does not fetch code, configure secrets, supervise processes, install cron or perform health requests. Never use `migrate:fresh`, `db:wipe`, `DROP DATABASE`, or an unreviewed rollback against production commerce data.

## Health, logs and monitoring

- `/health/live` proves the Laravel process responds. It does not test dependencies.
- `/health/ready` runs only a minimal database query. It returns 503 with `database: false` on failure.
- Neither endpoint checks Mollie or mail, and neither returns exception text, credentials, SQL or paths.

Production logs should go to captured stderr or rotated daily files. Financial logs use internal IDs, order numbers where useful, provider IDs and exception classes—never credentials, authorization headers, cookies, addresses or raw customer/provider payloads. Failed jobs emit sanitized job/queue context and are stored in `failed_jobs`.

Laravel's exception handler and PSR/Monolog channels are the integration points for a future error-monitoring service. No vendor SDK or DSN is committed. Until a provider is chosen, alert from hosting logs and uptime checks on HTTP 5xx, readiness failures, failed jobs/backlog, reconciliation failures, backup failure, disk capacity, database availability and TLS expiry.

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
