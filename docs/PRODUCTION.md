# RAOZA Production Runbook — v0.14

## Environments
Use separate Local, Staging and Production databases, Mollie keys, mail credentials, APP_KEY values and URLs. Never copy a production `.env` into source control.

## Production baseline
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://raoza.nl`
- strong unique `APP_KEY`
- PostgreSQL account limited to the RAOZA database
- `SESSION_SECURE_COOKIE=true`
- HTTPS enforced at the reverse proxy
- real transactional mail transport configured
- Mollie live key only after staging/test-key checkout is accepted
- `ANALYTICS_PROVIDER=none` until consent-aware provider configuration is deliberately enabled

## Release gate
CI/staging must pass Composer validation/install, TypeScript typecheck, frontend build, Laravel automated tests and migration status. A PHP syntax pass alone is not release approval.

## Deploy
Prefer immutable/versioned releases with a `current` symlink. Run `scripts/deploy.sh` only after CI succeeds. Back up before risky schema/data operations. Verify `/health/ready`, queue workers, checkout and admin login after deploy.

## Queue
Transactional email and other queued work require a continuously supervised worker. A systemd example is in `deployment/systemd/raoza-queue.service`. Monitor failed jobs and restart workers after deploy.

## Health
- `/health/live`: process/app liveness; intentionally does not test dependencies.
- `/health/ready`: verifies database readiness and returns 503 when unavailable.
Neither endpoint exposes credentials or exception details.

## Backups
`scripts/backup-postgres.sh` creates a PostgreSQL custom-format dump plus SHA-256 checksum and applies local retention. Production must also copy backups to encrypted off-host storage. Database backup is insufficient for product/content media; back up persistent uploaded assets separately.

A backup is not trusted until restore has been tested in an isolated database. `scripts/restore-postgres.sh` requires `ALLOW_DATABASE_RESTORE=yes` to reduce accidental use.

## Monitoring
At minimum alert on: HTTP 5xx rate, `/health/ready` failures, queue failures/backlog, payment webhook errors, failed scheduled backups, disk capacity, database availability and TLS expiry. Use a production error tracker/log aggregator without sending secrets, passwords or payment-card data.

## Rollback
Application rollback means switching to the previous known-good release and restarting workers. Database migrations require forward-compatible design or an explicit tested rollback plan; never blindly run destructive `migrate:rollback` in production.

## Dependency lockfiles
This historical project snapshot does not yet contain `composer.lock` or `package-lock.json`. v0.14 therefore uses normal dependency installation rather than falsely assuming lockfiles exist. Before v1.0, generate and review both lockfiles in a trusted development/CI environment, commit them, then switch production/CI Node installs to `npm ci`. Production releases should ultimately install exactly reviewed dependency versions.
