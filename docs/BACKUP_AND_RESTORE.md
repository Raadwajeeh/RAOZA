# Backup and restore

Backups are scheduled operational jobs, never web-request work. Store them outside the application release and web root, and protect both independent business-data sets:

- The configured production database: orders, payments, refunds, customers, inventory, catalog, content and operational records.
- Persistent uploaded media under `storage/app/public`, including Admin-uploaded product images under `products/<product-id>`.

Do not treat caches, logs, sessions, queued runtime data, compiled views, `vendor`, `node_modules`, frontend builds or temporary test files as recovery sources. Source code and lockfiles come from Git or release artifacts.

Copy successful database archives, checksums and corresponding media snapshots to access-restricted, encrypted off-host storage. Use separate least-privilege credentials, encryption in transit and at rest, and versioning or immutability where available. Never place a dump under `public/` or commit backup credentials or archives.

The owner must approve recovery objectives and retention. Until then, use 14 daily copies plus 8 weekly copies as a planning baseline, back up at least daily and immediately before migrations, and transfer the copy off-host immediately. The real RPO is no better than the backup or point-in-time-recovery interval; the real RTO must be measured with a timed restore drill. Database and media schedules must remain aligned.

Backups contain personal and order data. Limit operator access, audit restore and download activity, and delete expired copies only under the approved retention policy. A valid checksum or readable archive detects structural corruption but does not prove application recovery; only an isolated restore and application/data verification does that.

## MariaDB on Hostinger

### Credentials

Create `/home/ACCOUNT/.config/raoza-backup.cnf` with permissions `600`:

```ini
[client]
host=HOSTINGER_DATABASE_HOST
port=3306
user=HOSTINGER_BACKUP_USER
password=HOSTINGER_BACKUP_PASSWORD
default-character-set=utf8mb4
```

Prefer a dedicated least-privilege backup account if Hostinger supports it. Configure the protected file path, not the credentials, in cron. Never pass the password as a command argument.

### Backup

```bash
export DB_DATABASE=HOSTINGER_DATABASE_NAME
export BACKUP_DIR=/home/ACCOUNT/domains/raoza.raadwajeeh.com/backups
export BACKUP_RETENTION_DAYS=14
export BACKUP_MYSQL_DEFAULTS_FILE=/home/ACCOUNT/.config/raoza-backup.cnf
bash /home/ACCOUNT/domains/raoza.raadwajeeh.com/raoza-app/scripts/backup-mariadb.sh
```

The script uses `mariadb-dump` or `mysqldump`, a consistent InnoDB transaction, routines/events/triggers, timestamped gzip output, atomic rename and SHA-256 verification. It refuses a public backup directory and deletes only matching expired backup files in the explicitly configured directory. A nonzero exit must alert the operator.

Configure the Hostinger cron frequency from the accepted recovery-point loss; daily is appropriate only when losing up to a day of orders is acceptable. Media backup scope is `shared/storage/app/public`. Preserve ownership, paths and timestamps.

For the first Hostinger conversion, put the site in maintenance mode before creating paired database and filesystem backups. Archive both existing `raoza-app` and `public_html` as described in [HOSTINGER_DEPLOYMENT](HOSTINGER_DEPLOYMENT.md). That archive intentionally contains the existing `.env` and may contain private uploads, so keep it under the mode-700 private backup directory, create a SHA-256 sidecar, and never place it in `public_html` or a release.

### Isolated restore drill

Create an empty database whose name ends in `_restore_verify` and a client file authorized only for it. Never point restore variables at development, staging or production.

```bash
export APP_ENV=restore-verification
export ALLOW_DATABASE_RESTORE=yes
export DB_DATABASE=HOSTINGER_PRODUCTION_DATABASE_NAME
export RESTORE_DB_DATABASE=raoza_restore_verify
export BACKUP_MYSQL_DEFAULTS_FILE=/home/ACCOUNT/.config/raoza-restore-verify.cnf
bash scripts/restore-mariadb.sh /private/backups/raoza-mariadb-<timestamp>.sql.gz
```

Configure an isolated application copy to the restored database and media, disable outbound mail and payment side effects, and run:

```bash
php artisan migrate:status
php artisan production:preflight
curl -fsS https://isolated-restore-host/health/ready
```

Verify table counts, six expected official products, sampled orders/payments/refunds, inventory invariants, Admin login, and multiple static and uploaded images. Record the date, archive/checksum, MariaDB version, duration, tester and result.

Complete this drill before the first directory/database migration. MariaDB DDL may not be transactionally reversible, so recover a failed first migration by restoring into a newly created database and approving a connection cutover; never import a dump over the failed database.

## PostgreSQL

### Backup

Run `scripts/backup-postgres.sh` from a protected scheduler with PostgreSQL credentials and TLS settings supplied by the host secret store. Set an absolute `BACKUP_DIR` outside the application release and web root. The script maps `DB_SSLMODE`, `DB_SSLROOTCERT`, `DB_SSLCERT` and `DB_SSLKEY` to libpq for `pg_dump`, creates a custom-format dump, creates and rechecks a SHA-256 checksum, verifies that `pg_restore` can read the archive table of contents, and deletes only matching local backup files older than `BACKUP_RETENTION_DAYS` (default 14).

If losing up to one day of paid orders is unacceptable, select managed PostgreSQL point-in-time recovery and document and test the provider-specific restore path.

### Migration preservation gate

Before a production migration, record and review:

- the exact release SHA, pending migration filenames and framework/PHP/PostgreSQL versions;
- statements that take blocking locks, rewrite a large table, drop or rename data, or assume that new code is active;
- compatibility with outgoing and incoming code during the traffic-control window;
- representative counts and financial/inventory aggregates: orders, payments by status, refunds, inventory on-hand/reserved, movements, discount usages and product media;
- the verified backup path and checksum, off-host-copy status and latest successful restore-drill evidence;
- free database/disk capacity and an operator-approved abort threshold.

After migration, compare the recorded counts and aggregates, inspect `migrate:status`, verify that no unexpected status changes or duplicate movements occurred, and retain the evidence with the release record. Schema rollback is not the default after new orders can exist.

### Isolated restore drill

Never restore over the local development or production database:

1. Select a dump and verify its adjacent checksum with `sha256sum -c`.
2. Provision an empty PostgreSQL database whose name ends in `_restore_verify`, with network access restricted to the drill runner.
3. Prepare a restore-only environment: `APP_ENV=restore-verification`, `APP_DEBUG=false`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `PAYMENT_PROVIDER=demo`, `RAOZA_DEMO_MODE=false`, no Mollie key, and a non-public temporary media copy.
4. Set `DB_DATABASE` to the source database name only for the safety comparison, then run:

   ```bash
   ALLOW_DATABASE_RESTORE=yes APP_ENV=restore-verification \
     RESTORE_DB_DATABASE=raoza_restore_verify \
     scripts/restore-postgres.sh /secure/path/raoza-YYYYMMDDTHHMMSSZ.dump
   ```

5. Point a separate RAOZA verification checkout at the restored target. Keep mail non-delivering and Mollie credentials absent.
6. Run `php artisan migrate:status` and `php artisan about`; verify critical tables (`orders`, `payments`, `refunds`, `inventories`, `product_images`) and compare recorded counts/totals with the backup manifest or production snapshot.
7. Restore media into an isolated directory, compare file counts/checksums, and sample image paths referenced by `product_images`.
8. Boot the isolated application and verify `/health/live`, `/health/ready`, a read-only storefront page and an authenticated read-only Admin view. Do not initiate checkout, payment, refund, mail or webhook actions.
9. Record the source recovery point, dump/checksum identifiers, isolated target, operator, start/end time, PostgreSQL versions, row/aggregate comparisons, media checks, application checks and discrepancies. Destroy the isolated database/media only after retaining evidence and confirming the exact temporary targets.

The restore script refuses normal application environments, refuses restoring over `DB_DATABASE`, requires the `_restore_verify` suffix and does not create the target database. Its `--clean --if-exists` operation is destructive only inside the explicitly isolated target.

## Production recovery boundary

For either database engine, production recovery requires an incident decision: stop writes, preserve current evidence, restore into a new database, validate it, reconcile all commerce/provider activity after the recovery point, and only then approve a connection cutover. Never restore directly over the live database. A failed or overdue drill is a launch and operations blocker.
