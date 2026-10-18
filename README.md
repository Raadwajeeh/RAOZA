# RAOZA

RAOZA is a Laravel 12, React 19 and Inertia e-commerce application supporting PostgreSQL and MariaDB for a six-product editorial apparel catalog. Money is stored as integer minor units; checkout totals, stock, payment state and permissions are server-authoritative.

## Local development

Requirements: PHP 8.3+, Composer, Node.js/npm and PostgreSQL for normal local development. MariaDB production compatibility and its isolated test profile are documented in [docs/MARIADB.md](docs/MARIADB.md).

```bash
composer install
npm ci
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed --class=LocalQaSeeder
composer run dev:windows
```

Open `http://127.0.0.1:8080`. The Windows launcher pins Laravel to port 8080 and Vite to port 5175, checks both ports before starting, verifies that Laravel is in the `local` environment, and keeps the origins consistent. Its process-scoped overrides force demo payments, log-only mail, disabled analytics, and non-secure localhost cookies without rewriting `.env`. Add the optional local queue worker with `composer run dev:windows:queue`. Press `Ctrl+C` to stop the processes.

Do not start the PHP built-in server with `public/index.php` as its router (for example, `php -S 127.0.0.1:8080 -t public public/index.php`). That command routes existing public images through Laravel and returns HTML for image URLs. Use the launcher above or `php artisan serve --host=127.0.0.1 --port=8080 --no-reload`. The `--no-reload` flag avoids the Herd/PHP 8.4 Windows subprocess bind failure reproduced during Phase 11.

`DatabaseSeeder` and `LocalQaSeeder` are local/testing-only fixtures. The explicit command above is repeatable and does not reset the database; it creates synthetic products, stock, shipping methods, discounts, content, and `.test` accounts, then maps the catalog to the reviewed repository photography. Never run either seeder to bootstrap production. Local mail defaults to non-delivery and real payment, mail, hosting and owner data remain externally configured.

## Testing safety

The ordinary PHPUnit suite forcibly uses SQLite `:memory:` in `phpunit.xml`, clears database URL overrides, and has a runtime guard in `tests/TestCase.php`. Inherited `DB_*`, `DB_URL` or `DATABASE_URL` values cannot redirect `php artisan test` to PostgreSQL. PostgreSQL schema validation is a separate explicit CI job with its own isolated database.

```bash
php artisan test
npm run typecheck
npm run build
```

## Production boundary

Use `php artisan production:bootstrap <reviewed-manifest> --dry-run` before applying a reviewed catalog manifest. The command refuses demo mode and does not invent business settings, stock or secrets. Provision the first owner only from the server console with `admin:provision-owner`; emergency staff password recovery uses `admin:reset-password` and invalidates sessions.

Use `.env.production.example` only as a sanitized host-secret checklist. Before a deployment boundary, `php artisan production:preflight` validates the resolved configuration without printing secrets; the final separately authorized Live gate adds `--require-live-services`. Neither command provisions infrastructure or contacts Mollie/mail providers.

For the Hostinger staging target, follow [HOSTINGER_DEPLOYMENT](docs/HOSTINGER_DEPLOYMENT.md), [PRODUCTION_CATALOG](docs/PRODUCTION_CATALOG.md), [IMAGE_STORAGE](docs/IMAGE_STORAGE.md), [BACKUP_AND_RESTORE](docs/BACKUP_AND_RESTORE.md), and [PRODUCTION_READINESS](docs/PRODUCTION_READINESS.md). The provider-neutral [PRODUCTION](docs/PRODUCTION.md), [LAUNCH_CHECKLIST](docs/LAUNCH_CHECKLIST.md), and [SECURITY](SECURITY.md) remain supporting background. Production still requires owner-approved business/legal/catalog data and externally verified hosting, TLS, mail, Mollie, monitoring and restore behavior.

## Documentation index

- Deployment: [Hostinger procedure](docs/HOSTINGER_DEPLOYMENT.md), [first-deployment verification](docs/FIRST_DEPLOYMENT_VERIFICATION.md), and [production operations](docs/PRODUCTION.md).
- Readiness and owner decisions: [production readiness](docs/PRODUCTION_READINESS.md), [launch checklist](docs/LAUNCH_CHECKLIST.md), and the active [owner approval checklist](docs/OWNER_APPROVAL_CHECKLIST.md).
- Data and recovery: [production catalog](docs/PRODUCTION_CATALOG.md), [image storage](docs/IMAGE_STORAGE.md), and the canonical [backup and restore](docs/BACKUP_AND_RESTORE.md) procedures for MariaDB and PostgreSQL.
- Commerce and platform: [reconciliation](docs/COMMERCE_RECONCILIATION.md), [MariaDB compatibility](docs/MARIADB.md), and [security policy](SECURITY.md).

Completed phase reports and superseded audits are not active operating documentation. They are retained in the owner-managed external archive, outside the repository, with a SHA-256 manifest and their original filenames. Restore historical reports only for review; do not copy them back into the project root.
