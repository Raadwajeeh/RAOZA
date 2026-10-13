# Hostinger deployment

This runbook targets only `raoza.raadwajeeh.com`. Never run these commands from the `raadwajeeh.com` domain directory. Replace `/home/ACCOUNT` and release identifiers with reviewed values before execution.

For the audited risks, prerequisite checklist, rollback decision boundary, and local-versus-Hostinger verification record, read [FIRST_DEPLOYMENT_VERIFICATION](FIRST_DEPLOYMENT_VERIFICATION.md) before the first conversion.

## Runtime layout

```text
domains/raoza.raadwajeeh.com/
├── backups/                         # private database archives
├── releases/
│   ├── 20261013-<sha>/              # immutable application release
│   └── previous-release/
├── shared/
│   ├── .env                         # chmod 600; never in an archive
│   └── storage/                     # uploads, framework state and logs
├── raoza-app -> releases/20261013-<sha>
└── public_html/
    ├── index.php                    # deployment/hostinger template
    ├── .htaccess                    # Laravel/Apache rewrite template
    ├── build -> ../raoza-app/public/build
    ├── brand -> ../raoza-app/public/brand
    ├── campaign -> ../raoza-app/public/campaign
    ├── catalog -> ../raoza-app/public/catalog
    ├── demo -> ../raoza-app/public/demo
    ├── favicon.svg -> ../raoza-app/public/favicon.svg
    └── storage -> ../shared/storage/app/public
```

The active release is the only source for versioned public assets. `public_html` does not contain a second copied build or catalog. Uploaded media is independent of releases and remains available through the persistent `storage` link.

## Build on the development computer

Use a clean reviewed commit. The build script installs production PHP dependencies, installs locked Node dependencies, type-checks, builds Vite, validates every manifest reference, runs PHPUnit, removes `node_modules`, and creates a SHA-256 sidecar.

```powershell
git status --short
powershell -ExecutionPolicy Bypass -File scripts/build-hostinger-package.ps1
```

The result is under `artifacts/`. Do not upload `.env`, `node_modules`, a database dump, or the repository-level untracked `raoza-build*` artifacts. Upload the release ZIP and checksum to a private directory outside `public_html`.

## First-deployment prerequisites

The first conversion is deliberately manual and different from a subsequent release. `scripts/deploy-hostinger.sh` refuses an ordinary `raoza-app` directory and runs only after the managed layout exists.

Before the maintenance window:

- create a Hostinger account snapshot and confirm how it is restored;
- build the release from a clean reviewed commit and upload the ZIP plus checksum outside `public_html`;
- create the protected MariaDB client file described in [BACKUP_AND_RESTORE](BACKUP_AND_RESTORE.md);
- reserve enough free space for the existing application, web root, compressed filesystem archive, database dump and extracted release;
- obtain the exact Hostinger PHP CLI path and confirm PHP 8.4, Bash 4+, Composer, `tar`, `gzip`, `unzip`, `sha256sum`, `readlink`, and GNU `mv` with `-T` are available;
- confirm `APP_URL` and `PRODUCTION_CANONICAL_URL` are exactly `https://raoza.raadwajeeh.com`;
- configure a Mollie test key. Do not pass `--require-live-services`, create a real payment, or accept a real order for deployment readiness.

Record and canonicalize the domain path. Both basename checks are mandatory and prevent commands from running in `domains/raadwajeeh.com`:

```bash
export DOMAIN_ROOT=/home/ACCOUNT/domains/raoza.raadwajeeh.com
DOMAIN_ROOT="$(cd "$DOMAIN_ROOT" && pwd -P)"
test "$(basename "$DOMAIN_ROOT")" = raoza.raadwajeeh.com
test "$(basename "$(dirname "$DOMAIN_ROOT")")" = domains
cd -- "$DOMAIN_ROOT"
test -d raoza-app
test ! -L raoza-app
test -d public_html
printf 'Verified domain root: %s\n' "$DOMAIN_ROOT"
```

Stop if `raoza-app` is already a symlink: the managed layout may already be installed. Stop if either path is missing or has an unexpected type. Record filesystem, permission and upload inventories before changing anything:

```bash
find "$DOMAIN_ROOT/raoza-app" -xdev -type f | wc -l
du -sh "$DOMAIN_ROOT/raoza-app" "$DOMAIN_ROOT/public_html"
find "$DOMAIN_ROOT/raoza-app/storage/app/public" -type f 2>/dev/null | sort > /private/raoza-app-media.before.txt
if test -d "$DOMAIN_ROOT/public_html/storage" && test ! -L "$DOMAIN_ROOT/public_html/storage"; then
  find "$DOMAIN_ROOT/public_html/storage" -type f | sort > /private/public-html-media.before.txt
fi
test -f "$DOMAIN_ROOT/raoza-app/.env"
stat -c '%a %U:%G %n' "$DOMAIN_ROOT/raoza-app/.env" "$DOMAIN_ROOT/raoza-app/storage" "$DOMAIN_ROOT/public_html"
```

Test symbolic links without touching either live directory. Remove only the named probe:

```bash
ln -s . "$DOMAIN_ROOT/.raoza-symlink-probe"
test -L "$DOMAIN_ROOT/.raoza-symlink-probe"
test "$(readlink "$DOMAIN_ROOT/.raoza-symlink-probe")" = .
rm -- "$DOMAIN_ROOT/.raoza-symlink-probe"
```

Hostinger must also allow Apache to follow these links and honor `mod_rewrite` directives in `public_html/.htaccess`. Shell link creation alone proves neither capability. Verify both in the post-cutover HTTP checks and roll back the directory names on an Apache 403/500.

## First-deployment backups

Enter maintenance mode before taking the paired database/media backups so new writes cannot make them diverge. If the existing application cannot enter maintenance mode, stop and establish another maintenance mechanism before continuing:

```bash
cd "$DOMAIN_ROOT/raoza-app"
php artisan down --retry=60 --refresh=15
```

Create a private filesystem archive of both existing directories. `tar` archives symlinks as links by default and therefore does not follow a `public_html/storage` symlink. Verify the archive before proceeding:

```bash
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$DOMAIN_ROOT/backups"
chmod 700 "$DOMAIN_ROOT/backups"
FILE_BACKUP="$DOMAIN_ROOT/backups/raoza-files-$STAMP.tar.gz"
tar -C "$DOMAIN_ROOT" -czf "$FILE_BACKUP.part" raoza-app public_html
gzip -t "$FILE_BACKUP.part"
mv -- "$FILE_BACKUP.part" "$FILE_BACKUP"
(cd "$DOMAIN_ROOT/backups" && sha256sum "$(basename "$FILE_BACKUP")" > "$(basename "$FILE_BACKUP").sha256")
(cd "$DOMAIN_ROOT/backups" && sha256sum -c "$(basename "$FILE_BACKUP").sha256")
tar -tzf "$FILE_BACKUP" | grep -E '^(raoza-app/\.env|raoza-app/storage/|public_html/)'
```

Run the reviewed MariaDB backup script exactly as documented in [BACKUP_AND_RESTORE](BACKUP_AND_RESTORE.md). Verify the release archive first. If the old application does not contain that reviewed script, extract only it into a private tool path and inspect it before execution; do not activate the release to obtain it:

```bash
ARCHIVE=/private/releases/raoza-<sha>.zip
test -f "$ARCHIVE" && test -f "$ARCHIVE.sha256"
(cd "$(dirname "$ARCHIVE")" && sha256sum -c "$(basename "$ARCHIVE").sha256")
BACKUP_SCRIPT="$DOMAIN_ROOT/raoza-app/scripts/backup-mariadb.sh"
mkdir -m 700 -p "$DOMAIN_ROOT/private-tools"
if test ! -f "$BACKUP_SCRIPT"; then
  unzip -p "$ARCHIVE" scripts/backup-mariadb.sh > "$DOMAIN_ROOT/private-tools/backup-mariadb.sh"
  chmod 700 "$DOMAIN_ROOT/private-tools/backup-mariadb.sh"
  BACKUP_SCRIPT="$DOMAIN_ROOT/private-tools/backup-mariadb.sh"
fi
# Export the BACKUP_* and DB_DATABASE values from BACKUP_AND_RESTORE.md first.
bash "$BACKUP_SCRIPT"
```

Do not continue until the backup command returns zero, its `.sql.gz` and `.sha256` exist, `gzip -t` passes, and the checksum verifies. A structural dump check is not a restore drill; retain the Hostinger snapshot as the independent recovery layer.

## Prepare shared state and the first release

For a fresh conversion, `shared` must not already exist. If it does, treat it as evidence of an earlier partial attempt and reconcile it manually instead of overwriting it:

```bash
test ! -e "$DOMAIN_ROOT/shared"
test ! -e "$DOMAIN_ROOT/releases"
mkdir -m 700 "$DOMAIN_ROOT/shared"
mkdir -m 755 "$DOMAIN_ROOT/releases"
install -m 600 "$DOMAIN_ROOT/raoza-app/.env" "$DOMAIN_ROOT/shared/.env"
cp -a "$DOMAIN_ROOT/raoza-app/storage" "$DOMAIN_ROOT/shared/storage"
test -d "$DOMAIN_ROOT/shared/storage/app/public"
```

Edit only `shared/.env` for the managed staging release. Preserve the existing `APP_KEY` and database credentials; set the exact staging HTTPS URL, a new `APP_RELEASE`, production-safe session/cache/queue/storage values, SMTP staging settings, and a Mollie test key using `.env.production.example` as the checklist. Never copy the template over the preserved file and never print secrets to the terminal. Reapply and verify its permissions afterward:

```bash
chmod 600 "$DOMAIN_ROOT/shared/.env"
test "$(stat -c '%a' "$DOMAIN_ROOT/shared/.env")" = 600
```

If `public_html/storage` is a real directory rather than a symlink, it may be a second upload store. If only that directory contains uploads, copy its contents with metadata into shared storage. If both locations contain files, compare them and stop on any conflict; never merge or overwrite blindly:

```bash
APP_MEDIA="$DOMAIN_ROOT/shared/storage/app/public"
WEB_MEDIA="$DOMAIN_ROOT/public_html/storage"
if test -d "$WEB_MEDIA" && test ! -L "$WEB_MEDIA"; then
  if test -n "$(find "$APP_MEDIA" -type f -print -quit)"; then
    diff -qr "$APP_MEDIA" "$WEB_MEDIA"
  else
    cp -a "$WEB_MEDIA/." "$APP_MEDIA/"
  fi
fi
find "$APP_MEDIA" -type f | wc -l
du -sh "$APP_MEDIA"
```

Extract only the checksum-verified release. Reject unsafe archive paths, preserve the release storage skeleton rather than recursively deleting it, then attach shared state:

```bash
RELEASE_ID=<release-id>
RELEASE="$DOMAIN_ROOT/releases/$RELEASE_ID"
test -f "$ARCHIVE" && test -f "$ARCHIVE.sha256"
(cd "$(dirname "$ARCHIVE")" && sha256sum -c "$(basename "$ARCHIVE").sha256")
! unzip -Z1 "$ARCHIVE" | grep -Eq '(^/|(^|/)\.\.(/|$)|\\)'
test ! -e "$RELEASE"
mkdir -- "$RELEASE"
unzip -q "$ARCHIVE" -d "$RELEASE"
test -f "$RELEASE/vendor/autoload.php"
test -f "$RELEASE/public/build/manifest.json"
mv -- "$RELEASE/storage" "$DOMAIN_ROOT/backups/release-storage-skeleton-$RELEASE_ID"
ln -s ../../shared/storage "$RELEASE/storage"
ln -s ../../shared/.env "$RELEASE/.env"
chmod -R u+rwX,go-rwx "$DOMAIN_ROOT/shared/storage"
chmod -R u+rwX,go-rwx "$RELEASE/bootstrap/cache"
cd "$RELEASE"
composer validate --strict
composer check-platform-reqs --no-dev
php artisan production:verify-build
php artisan production:preflight
php artisan migrate:status
php artisan schedule:list
```

`production:preflight` checks resolved configuration and MariaDB compatibility but does not contact Mollie or SMTP. It accepts a syntactically valid Mollie test key. Do not use `--require-live-services` for staging readiness.

Prepare, but do not activate, the managed web root and application link:

```bash
PUBLIC_NEXT="$DOMAIN_ROOT/public_html.next-$RELEASE_ID"
APP_NEXT="$DOMAIN_ROOT/raoza-app.next-$RELEASE_ID"
test ! -e "$PUBLIC_NEXT" && test ! -e "$APP_NEXT"
mkdir -m 755 "$PUBLIC_NEXT"
install -m 644 "$RELEASE/deployment/hostinger/public_html/index.php" "$PUBLIC_NEXT/index.php"
install -m 644 "$RELEASE/deployment/hostinger/public_html/.htaccess" "$PUBLIC_NEXT/.htaccess"
ln -s ../raoza-app/public/build "$PUBLIC_NEXT/build"
ln -s ../raoza-app/public/brand "$PUBLIC_NEXT/brand"
ln -s ../raoza-app/public/campaign "$PUBLIC_NEXT/campaign"
ln -s ../raoza-app/public/catalog "$PUBLIC_NEXT/catalog"
ln -s ../raoza-app/public/demo "$PUBLIC_NEXT/demo"
ln -s ../raoza-app/public/favicon.svg "$PUBLIC_NEXT/favicon.svg"
ln -s ../shared/storage/app/public "$PUBLIC_NEXT/storage"
ln -s "releases/$RELEASE_ID" "$APP_NEXT"
test "$(readlink "$APP_NEXT")" = "releases/$RELEASE_ID"
```

The front controller resolves `../raoza-app`, Laravel keeps its internal public path at `<release>/public`, Vite reads `<release>/public/build/manifest.json`, and Apache serves the same build through `public_html/build -> ../raoza-app/public/build`. Do not call `usePublicPath(public_html)`: doing so would make Laravel and the packaged manifest disagree.

## First cutover and database migration

Keep the old application in maintenance mode. Move each existing directory to a timestamped recoverable name, then activate the prepared link/root. Run one command at a time and stop at the first failure:

```bash
cd "$DOMAIN_ROOT"
OLD_APP="raoza-app.pre-managed-$STAMP"
OLD_PUBLIC="public_html.pre-managed-$STAMP"
mv -- raoza-app "$OLD_APP"
mv -- "$APP_NEXT" raoza-app
mv -- public_html "$OLD_PUBLIC"
mv -- "$PUBLIC_NEXT" public_html
test -L raoza-app
test "$(readlink raoza-app)" = "releases/$RELEASE_ID"
test -f public_html/index.php && test -f public_html/.htaccess
```

Run forward migrations only. Never run `migrate:fresh`, `db:wipe`, or automatic migration rollback:

```bash
cd "$DOMAIN_ROOT/raoza-app"
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
php artisan optimize
php artisan queue:restart
php artisan up
```

Immediately run the HTTP checks below. An Apache 403/500, missing asset, broken uploaded image, or non-Laravel 404 is a failed cutover. Do not delete either `*.pre-managed-*` directory, the filesystem archive, database backup, or Hostinger snapshot until staging acceptance and a rollback rehearsal are recorded.

## Database and catalog initialization

Only for a confirmed empty staging catalog. Inspect the database first; an existing catalog must be reviewed and migrated deliberately rather than bootstrapped over:

```bash
cd "$DOMAIN_ROOT/raoza-app"
php artisan migrate:status
php artisan migrate --force
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --dry-run
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --confirm
php artisan admin:provision-owner owner@example.com --name="Owner name" --confirm
php artisan optimize
```

Never run `migrate:fresh`, `db:wipe`, `DatabaseSeeder`, `LocalQaSeeder`, `OfficialCatalogSeeder`, or `StorefrontContentSeeder` on staging/production. The reviewed production manifest is the only initial catalog workflow.

## Subsequent releases

Create and verify a pre-migration backup, then run:

```bash
export DOMAIN_ROOT=/home/ACCOUNT/domains/raoza.raadwajeeh.com
export VERIFIED_BACKUP_FILE="$DOMAIN_ROOT/backups/raoza-mariadb-<timestamp>.sql.gz"
bash "$DOMAIN_ROOT/raoza-app/scripts/deploy-hostinger.sh" /private/releases/raoza-<sha>.zip <release-id>
```

The script verifies both checksums, extracts an immutable release, attaches shared state, validates PHP/Vite/config/database readiness, enables maintenance mode, applies forward migrations, optimizes Laravel, switches `raoza-app`, restarts queue workers logically, and exits maintenance mode. It never runs Node on Hostinger and never deletes an older release.

## Hostinger cron

Configure one scheduler invocation every minute:

```cron
* * * * * cd /home/ACCOUNT/domains/raoza.raadwajeeh.com/raoza-app && /usr/bin/php artisan schedule:run >> /home/ACCOUNT/domains/raoza.raadwajeeh.com/shared/storage/logs/scheduler.log 2>&1
```

Shared hosting generally cannot supervise a permanent worker. Drain the database queue with a bounded cron command every minute (use Hostinger's actual PHP path):

```cron
* * * * * cd /home/ACCOUNT/domains/raoza.raadwajeeh.com/raoza-app && /usr/bin/php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --backoff=30 --timeout=45 >> /home/ACCOUNT/domains/raoza.raadwajeeh.com/shared/storage/logs/queue.log 2>&1
```

Confirm Hostinger prevents overlapping cron invocations or wrap the queue command with an available lock utility. Otherwise use `QUEUE_CONNECTION=sync` only after accepting its request-latency/failure tradeoff; do not silently leave database jobs unprocessed.

## Smoke checks

```bash
curl -fsS https://raoza.raadwajeeh.com/health/live
curl -fsS https://raoza.raadwajeeh.com/health/ready
curl -fsSI https://raoza.raadwajeeh.com/shop
curl -fsSI https://raoza.raadwajeeh.com/build/manifest.json
php "$DOMAIN_ROOT/raoza-app/artisan" schedule:list
php "$DOMAIN_ROOT/raoza-app/artisan" queue:failed
```

Also open `/`, `/shop`, one product, cart, checkout, and `/admin/login`; verify an uploaded image URL; inspect generated HTTPS return/webhook URLs; and confirm `/nonexistent` is a Laravel 404 rather than an Apache 404. A manifest request may be blocked by server policy; the decisive asset check is that the JS/CSS URLs emitted by the page return 200.

## First-deployment rollback

If cutover fails before `php artisan migrate --force` changes the database, restore the original directory names. Preserve the failed managed paths for inspection:

```bash
cd "$DOMAIN_ROOT"
test -d "$OLD_APP" && test -d "$OLD_PUBLIC"
test -L raoza-app && mv -- raoza-app "raoza-app.failed-$STAMP"
test -d public_html && mv -- public_html "public_html.failed-$STAMP"
mv -- "$OLD_APP" raoza-app
mv -- "$OLD_PUBLIC" public_html
php "$DOMAIN_ROOT/raoza-app/artisan" up
```

If a migration fails or any migration has been applied, do not run `migrate:rollback` and do not import the dump over the modified database. Keep the site in maintenance mode. Restore the verified pre-deployment dump into a newly created database, validate it with the old application and restored media, then approve an `.env` database cutover and the directory rollback above. This is why an isolated restore rehearsal is a first-deployment prerequisite. If every applied migration has separately been proven backward compatible, an authorized operator may restore the old code while leaving the forward schema in place, but that compatibility must not be assumed.

## Managed-release rollback

For later managed releases, record the old target first with `readlink raoza-app`. Code rollback is safe only when applied migrations remain backward compatible:

```bash
cd "$DOMAIN_ROOT"
php raoza-app/artisan down --retry=60
ln -sfn releases/<previous-release-id> raoza-app
php raoza-app/artisan optimize
php raoza-app/artisan queue:restart
php raoza-app/artisan up
```

Do not run migration rollback automatically. For a destructive or incompatible data migration, keep traffic stopped and restore only into a separately created verification database. Reconcile every order/payment created after the backup before any approved database cutover.
