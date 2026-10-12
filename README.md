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
npm run build
php artisan serve
```

`DatabaseSeeder` and `LocalQaSeeder` are non-production fixtures. Never run them to bootstrap production. Local mail defaults to non-delivery and real payment, mail, hosting and owner data remain externally configured.

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

See [docs/PRODUCTION.md](docs/PRODUCTION.md), [docs/LAUNCH_CHECKLIST.md](docs/LAUNCH_CHECKLIST.md), and [SECURITY.md](SECURITY.md). Production still requires owner-approved business/legal/catalog data and externally configured hosting, TLS, mail, Mollie Live, monitoring and backups.
