# MariaDB backup and restore

Backups are scheduled jobs, never web-request work. Store them under the private domain directory, not `public_html` and not a release.

## Credentials

Create `/home/ACCOUNT/.config/raoza-backup.cnf` with permissions `600`:

```ini
[client]
host=HOSTINGER_DATABASE_HOST
port=3306
user=HOSTINGER_BACKUP_USER
password=HOSTINGER_BACKUP_PASSWORD
default-character-set=utf8mb4
```

Prefer a dedicated least-privilege backup account if Hostinger supports it. The path, not the credentials, is configured in the cron environment. Never pass a password as a command argument or commit this file.

## Backup

```bash
export DB_DATABASE=HOSTINGER_DATABASE_NAME
export BACKUP_DIR=/home/ACCOUNT/domains/raoza.raadwajeeh.com/backups
export BACKUP_RETENTION_DAYS=14
export BACKUP_MYSQL_DEFAULTS_FILE=/home/ACCOUNT/.config/raoza-backup.cnf
bash /home/ACCOUNT/domains/raoza.raadwajeeh.com/raoza-app/scripts/backup-mariadb.sh
```

The script uses `mariadb-dump` or `mysqldump`, a consistent InnoDB transaction, routines/events/triggers, timestamped gzip output, atomic rename and SHA-256 verification. It refuses a public backup directory and deletes only matching expired backup files in the explicitly configured directory. A nonzero exit must alert the operator.

Configure the Hostinger cron frequency based on accepted recovery-point loss (daily is only appropriate if losing up to a day of orders is acceptable). Copy successful database archives, checksums, and the corresponding media snapshot off-server when configured. External storage is a backup destination, not a storefront dependency.

Media backup scope is `shared/storage/app/public`. Preserve file ownership, paths and timestamps. Logs, caches, sessions and compiled views do not replace database/media backups.

For the first Hostinger conversion, put the site in maintenance mode before creating the paired database and filesystem backups. In addition to the database dump, archive both existing `raoza-app` and `public_html` as described in [HOSTINGER_DEPLOYMENT](HOSTINGER_DEPLOYMENT.md). That archive intentionally contains the existing `.env` and may contain private uploads, so it must remain under the mode-700 private backup directory, have a SHA-256 sidecar, and never be placed in `public_html` or a release.

## Restore drill

Create an empty isolated database whose name ends in `_restore_verify` and a client file authorized only for that database. Never point the restore variables at staging/production.

```bash
export APP_ENV=restore-verification
export ALLOW_DATABASE_RESTORE=yes
export DB_DATABASE=HOSTINGER_PRODUCTION_DATABASE_NAME
export RESTORE_DB_DATABASE=raoza_restore_verify
export BACKUP_MYSQL_DEFAULTS_FILE=/home/ACCOUNT/.config/raoza-restore-verify.cnf
bash scripts/restore-mariadb.sh /private/backups/raoza-mariadb-<timestamp>.sql.gz
```

Then configure an isolated application copy to the restored database and restored media and run:

```bash
php artisan migrate:status
php artisan production:preflight
curl -fsS https://isolated-restore-host/health/ready
```

Verify table counts, six expected official products, sampled orders/payments/refunds, inventory invariants, admin login, and multiple static/uploaded images. Record date, archive/checksum, MariaDB version, duration, tester and result. Until this drill passes, backups are created and structurally checked but are not proven recoverable.

Complete this isolated restore drill before the first directory/database migration, not after it. MariaDB DDL may not be transactionally reversible, so a failed first migration is recovered by restoring into a newly created database and approving a connection cutover; never import a dump over the failed staging database.

Production recovery requires an incident decision: stop writes, preserve current evidence, restore into a new database, validate it, reconcile all post-backup commerce/provider activity, and only then approve a connection cutover. Never restore directly over the live database.
