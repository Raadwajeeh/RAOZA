# Backup and restore verification

## What must be protected

Back up both independent business-data sets:

- PostgreSQL: orders, payments, refunds, customers, inventory, catalog, content and operational records.
- Persistent uploaded media: `storage/app/public`, currently including Admin-uploaded product images under `products/<product-id>`.

Do not back up caches, logs, sessions, queued runtime data as a primary recovery source, compiled views, `vendor`, `node_modules`, frontend builds or temporary test files. Source code and lockfiles come from Git/release artifacts.

## Production strategy

Run `scripts/backup-postgres.sh` from a protected scheduler with PostgreSQL credentials/TLS settings supplied by the host secret store and an absolute `BACKUP_DIR` outside the application release and web root. The script maps `DB_SSLMODE`, `DB_SSLROOTCERT`, `DB_SSLCERT` and `DB_SSLKEY` to libpq for `pg_dump`, creates a custom-format dump, creates and rechecks a SHA-256 checksum, verifies that `pg_restore` can read the archive table of contents, and deletes only matching local backup files older than `BACKUP_RETENTION_DAYS` (default 14).

Those checks detect transfer corruption and an unreadable archive; they do not prove that the data is complete or restorable as an application. Reliability requires a successful isolated restore plus application/data verification.

Copy database dumps and persistent media to access-restricted, encrypted off-host storage. The destination can be S3-compatible or another managed backup system; no provider is assumed. Encrypt in transit and at rest, use separate least-privilege credentials, enable object versioning/immutability where available, and keep credentials outside Git. A practical starting policy is 14 daily copies plus 8 weekly copies, adjusted after the business sets recovery objectives and privacy retention requirements.

Backups contain personal and order data. Limit operator access, audit restore/download access, and delete expired copies under the approved retention policy. Never place dumps under `public/` or commit them.

The owner must approve recovery objectives. Until then, use these planning targets rather than promises: database backup at least daily and immediately before migrations, off-host transfer immediately after creation, an RPO no better than the actual backup/PITR interval, and an RTO measured by a timed restore drill. If losing up to one day of paid orders is unacceptable, select managed PostgreSQL point-in-time recovery and document/test the provider-specific restore path. Media backups need a matching schedule; database references and media can otherwise recover to different points.

## Migration compatibility and preservation gate

Before a production migration, record and review:

- exact release SHA, pending migration filenames and framework/PHP/PostgreSQL versions;
- whether each statement takes blocking locks, rewrites a large table, drops/renames data or assumes the new code is already active;
- compatibility with both the outgoing and incoming code during the traffic-control window;
- representative counts and financial/inventory aggregates: orders, payments by status, refunds, inventory on-hand/reserved, movements, discount usages and product media;
- verified backup path, checksum, off-host-copy status and latest successful restore-drill evidence;
- free database/disk capacity and an operator-approved abort threshold.

After migration, compare the recorded counts/aggregates, inspect `migrate:status`, verify no unexpected status changes or duplicate movements, and retain the evidence with the release record. Schema rollback is not the default once new orders can exist.

## Isolated restore drill

Never restore over the local development or production database. Use an isolated host/database and disable outbound side effects:

1. Select a dump and verify its adjacent checksum with `sha256sum -c`.
2. Provision an empty PostgreSQL database whose name ends in `_restore_verify`, with network access restricted to the drill runner.
3. Prepare a restore-only environment: `APP_ENV=restore-verification`, `APP_DEBUG=false`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `PAYMENT_PROVIDER=demo`, `RAOZA_DEMO_MODE=false`, no Mollie key, and a non-public temporary media disk/copy.
4. Set `DB_DATABASE` to the original/source database name only for the safety comparison, set `RESTORE_DB_DATABASE` to the isolated target, and run:

   ```bash
   ALLOW_DATABASE_RESTORE=yes APP_ENV=restore-verification \
     RESTORE_DB_DATABASE=raoza_restore_verify \
     scripts/restore-postgres.sh /secure/path/raoza-YYYYMMDDTHHMMSSZ.dump
   ```

5. Point a separate RAOZA verification checkout at the restored target. Keep mail non-delivering and Mollie credentials absent.
6. Run `php artisan migrate:status` and `php artisan about`; verify critical tables (`orders`, `payments`, `refunds`, `inventories`, `product_images`) and compare recorded counts/totals with the backup manifest or production snapshot.
7. Restore media into an isolated directory, compare file counts/checksums, and sample product-image paths referenced by `product_images`.
8. Boot the isolated application and verify `/health/live`, `/health/ready`, a read-only storefront page and an authenticated read-only Admin view. Do not initiate checkout, payment, refund, mail or webhook actions.
9. Record dump timestamp, recovery duration, checks, discrepancies and operator. Destroy the isolated database/media only after evidence is retained and the exact temporary targets are confirmed.

The restore script refuses normal application environments, refuses restoring over `DB_DATABASE`, and requires the target suffix `_restore_verify`. It does not create the target database. Its `--clean --if-exists` operation is destructive only inside that explicitly isolated target.

Record every drill: backup/checksum identifiers, source recovery point, isolated target identity, operator, start/end time, PostgreSQL versions, row/aggregate comparisons, media checks, application checks, discrepancies and destruction confirmation. A failed or overdue drill is a launch/operations blocker, not a documentation exception.
