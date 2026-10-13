# Production readiness

## Audit findings and repairs

| Area | Confirmed cause/risk | Repository response |
|---|---|---|
| Vite 500 | `public/build` is ignored by Git and the old server deploy runs `npm`, which Hostinger cannot do | local/CI release packaging builds assets; `production:verify-build` validates the manifest and every referenced file |
| Web root and 404s | standard `public/index.php` assumes it sits inside the app; no tracked Apache template existed for sibling `public_html` | Hostinger front controller resolves `../raoza-app`; `.htaccess` routes non-files to Laravel; public assets are controlled symlinks |
| Empty shop | migrations create schema, not catalog data; `DatabaseSeeder` intentionally skips production | reviewed, transactional `production:bootstrap` supports a clean database and safe reruns |
| Unsafe seed reuse | local seed data includes demo credentials, inventory, discounts, shipping and placeholder policies; `OfficialCatalogSeeder` assumes/changes demo records and archives unrelated active products | production runbook forbids those seeders and uses a separate manifest that does not delete/archive unrelated products |
| Catalog incompleteness | approved prices, SKUs, variants, opening stock, care and live shipping are absent from the repository | example fails closed; owner approval is required rather than inventing values |
| Uploaded media | public-disk paths are relative and valid, but copied releases would lose files | shared persistent storage and a stable `/storage` symlink; path validation and documented media backups |
| Backups | existing scripts are PostgreSQL-only | MariaDB dump/restore scripts with private credentials file, checksums, retention and isolated restore gates |
| Runtime jobs | systemd examples do not apply to shared hosting; database queue needs a worker | bounded Hostinger cron commands are documented for scheduler and queue |
| Database CI | default CI schema job covered PostgreSQL, not the target live database | MariaDB 11.8 schema, integrity and process-concurrency suite is the target integration job |

## Security/operations review

- Production preflight requires `APP_ENV=production`, debug off, HTTPS canonical URL, non-template key/release, MariaDB hardening, encrypted secure database sessions, durable queue, local persistent media, captured logs, Mollie, SMTP and demo mode off.
- Admin routes require authentication, a staff role and granular permissions. Owner provisioning uses a hidden strong-password prompt; local demo credentials are not part of production bootstrap.
- CSRF remains enabled except for the Mollie webhook. The webhook accepts only a provider payment identifier and fetches authoritative provider state; amount, currency and order reference are checked. Payment/inventory transitions and provider events are transactionally idempotent.
- Uploads exclude SVG/executable types, are size/count limited, use generated storage paths and are not served from private storage.
- Database sessions/cache/queue require their migrations. Queue and scheduler cron, SMTP delivery, Mollie webhook reachability and alerts must be proven on staging.
- File permissions must restrict `.env`, backup client files and backup archives to the account/PHP user. Only shared storage and bootstrap cache are writable.

## Controlled staging gate

The repository is suitable for a controlled staging deployment only after all mandatory items below are evidenced:

- [ ] Owner approves prices, SKU matrix, stock, care instructions and shipping truth in the private catalog manifest.
- [ ] Hostinger PHP 8.4 extensions pass `composer check-platform-reqs --no-dev`.
- [ ] Hostinger MariaDB identity, strict mode, UTC, InnoDB and `READ COMMITTED` pass `production:preflight`.
- [ ] Existing `.env` and uploaded media are preserved during the one-time layout conversion.
- [ ] Database backup completes and a restore drill succeeds in an isolated database.
- [ ] Scheduler and bounded queue cron run without overlap; failed-job and cron failure alerts reach an operator.
- [ ] SMTP sender/DNS and Mollie test credentials/webhook flows pass staging acceptance.
- [ ] Final legal pages, business identity, VAT rate, return/shipping promises and consent policy receive owner/legal/accounting approval.
- [ ] HTTPS smoke checks, non-Apache Laravel 404, asset URLs and uploaded media pass after deployment.

Do not enable Mollie Live or accept real orders while any business, legal, backup/restore, mail, cron or payment acceptance item remains open.

## Verification record

Executed locally on PHP 8.4.26 and Node 24.15.0:

- Composer strict manifest validation: passed.
- PHPUnit SQLite suite: 160 tests passed, 869 assertions.
- TypeScript typecheck and Vite production build: passed.
- Vite verification: 45 manifest entries and 46 referenced files passed.
- Composer locked dependency audit: no advisories.
- npm production dependency audit: no vulnerabilities.
- Pint on changed PHP files: passed. The codebase-wide Pint check still reports pre-existing style debt in many untouched files; no bulk formatting rewrite was applied.
- Bash deployment/backup scripts: syntax checked with Git Bash. PowerShell package script: parser check passed; the full package command was not run because it deliberately refuses a dirty worktree.
- MariaDB 11.8 integration/concurrency suite: configured in CI but not executed locally because no disposable MariaDB service was available. Never run it against Hostinger staging/production.
- Live SMTP, Mollie, cron, Apache, permissions and restore behavior: cannot be proven from the local repository and must be recorded during controlled staging operations.
