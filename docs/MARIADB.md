# MariaDB compatibility

RAOZA supports PostgreSQL for local development, but MariaDB is the authoritative live database for the Hostinger deployment. SQLite `:memory:` remains the default fast PHPUnit backend. Do not point any automated test command at a persistent development, QA, staging, or production database.

## Supported MariaDB profile

The verified profile is MariaDB 11.4.12, InnoDB, `utf8mb4` with `utf8mb4_unicode_ci`, strict SQL mode, UTC session time, and `READ COMMITTED` transaction isolation. The application preflight requires MariaDB 10.3 or newer and verifies these runtime properties. `READ COMMITTED` is intentional: commerce transactions serialize on row locks and must see claims committed while they were waiting.

Set `DB_CONNECTION=mysql`, the Hostinger-provided host/port/database/user/password, `DB_CHARSET=utf8mb4`, `DB_COLLATION=utf8mb4_unicode_ci`, and `DB_ISOLATION_LEVEL="READ COMMITTED"`. For a remote database, configure `MYSQL_ATTR_SSL_CA`; loopback or a Unix socket is accepted without a CA. Never commit the populated environment file.

Before deployment, run `php artisan production:preflight`. Review migrations on a disposable clone and take a verified backup before applying them. The integrity migration uses MariaDB CHECK constraints for stock and monetary invariants, a generated normalized discount code, and triggers plus a unique index to enforce one purchase analytics event per order.

## Isolated test suite

`phpunit.mariadb.xml` is opt-in and refuses to start unless the connection is MariaDB on `127.0.0.1`, the database and user names begin with `raoza_phase7d2_`, URLs are unset, and the account has no global privileges. Supply `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` only for a disposable local instance, then run:

```powershell
$env:DB_PORT='3307'
$env:DB_DATABASE='raoza_phase7d2_mariadb'
$env:DB_USERNAME='raoza_phase7d2_runner'
$env:DB_PASSWORD='<disposable-password>'
php vendor\bin\phpunit -c phpunit.mariadb.xml
```

The suite runs destructive migrations and independent-process concurrency tests. It must never be used with Hostinger or any long-lived database.

## Hostinger verification that remains external

Confirm the actual MariaDB version and enabled SQL mode, database host/port, TLS/CA availability, account privileges and storage-engine defaults with `production:preflight`. Worker/cron, backup/restore and deployment procedures are in the Hostinger-specific runbooks. No repository test or script in this audit connects to Hostinger or changes a live database.
