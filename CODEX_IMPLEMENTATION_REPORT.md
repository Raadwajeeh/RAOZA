# RAOZA post-repair implementation report

Generated: 2026-10-13 (Europe/Amsterdam)

## Review scope and provenance

This report describes the current canonical RAOZA working tree after the Hostinger deployment repair work. The baseline commit is `e96813ea6d274f46be274eea4780f3e8e8bb233e` on `main` (`Add verified MariaDB commerce compatibility`); the repair set is present as staged working-tree changes and is not represented by a later commit hash. The review archive contains the canonical application directories and the newly generated `public/build` output. It deliberately excludes local duplicate/copy trees, existing archives, runtime caches, `.env`, Git metadata, installed Node/PHP dependency trees, and any database/private-media payloads.

No Hostinger account was accessed and no deployment or remote operation was performed while producing the review package. Application source was not edited during packaging; this report is the only new repository document created for the package. Running `npm run build` refreshed ignored generated assets under `public/build`.

## Implemented repairs

### Hostinger release and web-root layout

- Added `deployment/hostinger/public_html/index.php` for the sibling `raoza-app` release layout and an Apache `.htaccess` template that routes non-files through Laravel.
- Added `scripts/build-hostinger-package.ps1`. From a clean reviewed commit it archives tracked source, installs optimized production Composer dependencies, installs locked Node dependencies, type-checks, builds Vite, verifies the build, runs PHPUnit, removes `node_modules`, writes `RELEASE`, and emits a SHA-256 sidecar.
- Added `scripts/deploy-hostinger.sh`. It verifies release and backup checksums, creates an immutable release, attaches shared `.env` and storage, validates the release, enables maintenance mode, applies forward migrations, optimizes Laravel, atomically switches the `raoza-app` symlink, restarts queues, and leaves older releases available.
- Documented one-time layout conversion, subsequent releases, cron, smoke checks, and non-destructive rollback in `docs/HOSTINGER_DEPLOYMENT.md`.

### Production build integrity

- Added `production:verify-build` and `App\Support\ViteBuildVerifier` to require a valid Vite manifest, a RAOZA application entry, safe relative asset paths, valid import references, and every referenced file.
- Added focused unit coverage and CI/build-script invocation of the verifier.
- Included the locally regenerated production build in `public/build` in this review ZIP. `node_modules` is excluded; reproduce it with `npm ci`, `npm run typecheck`, and `npm run build`.

### Production catalog initialization

- Hardened the transactional production bootstrap workflow for clean databases and safe reruns.
- The reviewed JSON manifest workflow validates required catalog fields, stable slugs/SKUs, SKU ownership, integer prices and stock, image path safety/existence, and demo-mode refusal.
- It does not reuse local/demo seeders, invent missing business facts, delete/archive unrelated products, or silently overwrite changed live stock. Zero opening stock no longer creates a meaningless zero-quantity inventory movement.
- Added tests for an intentionally incomplete example, portability/idempotency, six-product creation without legacy IDs, unsafe/remote/traversal/missing image rejection, demo-mode refusal, and zero-stock behavior.
- Documented owner-approval gaps and the exact dry-run/confirmation workflow in `docs/PRODUCTION_CATALOG.md`.

### Image persistence and safety

- Documented the distinction between versioned official assets (`public/brand`, `public/campaign`, `public/catalog`) and persistent admin uploads (`shared/storage/app/public/products`).
- Hostinger `public_html` uses controlled links to active versioned assets and persistent shared media, preventing releases from dropping uploaded images.
- Catalog bootstrap accepts only approved, safe, existing local paths. Existing upload validation continues to restrict type, size, count, generated names, authorization, and deletion scope.
- Added operational backup/restore guidance in `docs/IMAGE_STORAGE.md`.

### MariaDB backup and restore

- Added `scripts/backup-mariadb.sh` with a protected client defaults file, strict target validation, consistent transactional dump, gzip verification, SHA-256 verification, atomic rename, private-directory enforcement, and narrowly scoped retention.
- Added `scripts/restore-mariadb.sh` with explicit opt-in, restore-verification environment enforcement, source/target separation, `_restore_verify` target naming, gzip/checksum validation, and an isolated target database.
- Added `docs/BACKUP_AND_RESTORE.md`; database and public media are treated as separate but jointly required backup sets.

### Configuration, readiness, CI, and health

- Expanded `.env.production.example` as a sanitized Hostinger/MariaDB, persistent storage, durable queue/session, SMTP, Mollie, retention, and backup checklist without real secrets.
- Updated CI to build and verify production assets and added a MariaDB 11.8 commerce/schema job.
- Updated readiness health coverage to prove probes remain read-only/sessionless and forwarded HTTPS is not trusted without an explicit proxy allowlist.
- Updated `README.md` and `docs/MARIADB.md`; added `docs/PRODUCTION_READINESS.md` as the consolidated risks, repairs, and staging acceptance gate.

## Verification results

Executed locally on 2026-10-13:

| Check | Result |
|---|---|
| `composer validate --strict` | Passed |
| `npm run typecheck` | Passed |
| `npm run build` | Passed; 816 modules transformed |
| `php artisan production:verify-build` | Passed; 45 manifest entries and 46 referenced files |
| `php artisan test` | Passed; 160 tests, 869 assertions |
| `composer audit --locked --no-interaction` | Passed; no security advisories |
| `npm audit --omit=dev` | Passed; 0 vulnerabilities |
| Git Bash `bash -n` on deployment, release, backup, and restore scripts | Passed; 5 scripts |
| PowerShell parser check on `scripts/build-hostinger-package.ps1` | Passed |

The full Hostinger package script was not executed because it correctly refuses the current dirty/staged repair worktree. The production dependency tree (`vendor`) is therefore not included in this review archive. After the repair set is reviewed and committed, the clean-release build below installs it reproducibly from `composer.lock`.

## Unresolved and externally verifiable items

- Owner approval is still required for prices, the complete SKU/variant matrix, opening stock, care instructions, live shipping facts, store identity, VAT, policy/legal text, and consent-policy version.
- Hostinger PHP 8.4 extensions/platform requirements, actual domain paths, Apache behavior, symlink support, permissions, MariaDB identity/settings, TLS, cron non-overlap, queue processing, and alert delivery have not been verified locally.
- The MariaDB 11.8 integration/concurrency suite is configured in CI but was not run locally because no disposable MariaDB service was available. It must never be pointed at Hostinger staging or production.
- A real backup plus isolated restore drill, persistent-media restore, SMTP/DNS delivery, Mollie test payment/refund/webhook flow, HTTPS/404/assets/upload smoke tests, monitoring, and rollback rehearsal remain controlled staging acceptance work.
- Mollie Live and real ordering must remain disabled until all business, legal, backup/restore, mail, cron, payment, and smoke-test gates are signed off.

## Exact build and deployment commands

All placeholders must be replaced with reviewed values. The authoritative expanded procedure is `docs/HOSTINGER_DEPLOYMENT.md`; backup/restore detail is in `docs/BACKUP_AND_RESTORE.md`.

### 1. Build a deployable release on the development computer

Run only after the reviewed repair set is committed and `git status --short` is empty:

```powershell
git status --short
powershell -ExecutionPolicy Bypass -File scripts/build-hostinger-package.ps1
```

This creates `artifacts/raoza-<12-character-sha>.zip` and the matching `.sha256`. Upload both to a private Hostinger path outside `public_html`.

### 2. Create and verify the required pre-deployment MariaDB backup

```bash
export DB_DATABASE=HOSTINGER_DATABASE_NAME
export BACKUP_DIR=/home/ACCOUNT/domains/raoza.raadwajeeh.com/backups
export BACKUP_RETENTION_DAYS=14
export BACKUP_MYSQL_DEFAULTS_FILE=/home/ACCOUNT/.config/raoza-backup.cnf
bash /home/ACCOUNT/domains/raoza.raadwajeeh.com/raoza-app/scripts/backup-mariadb.sh
```

### 3. Deploy a subsequent immutable release

```bash
export DOMAIN_ROOT=/home/ACCOUNT/domains/raoza.raadwajeeh.com
export VERIFIED_BACKUP_FILE="$DOMAIN_ROOT/backups/raoza-mariadb-<timestamp>.sql.gz"
bash "$DOMAIN_ROOT/raoza-app/scripts/deploy-hostinger.sh" /private/releases/raoza-<sha>.zip <release-id>
```

### 4. Initialize a clean staging database and reviewed catalog once

```bash
cd "$DOMAIN_ROOT/raoza-app"
php artisan migrate:status
php artisan migrate --force
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --dry-run
php artisan production:bootstrap /private/reviewed/raoza-catalog.json --confirm
php artisan admin:provision-owner owner@example.com --name="Owner name" --confirm
php artisan optimize
```

Never run `migrate:fresh`, `db:wipe`, `DatabaseSeeder`, `LocalQaSeeder`, `OfficialCatalogSeeder`, or `StorefrontContentSeeder` on staging/production.

### 5. Post-deployment verification

```bash
curl -fsS https://raoza.raadwajeeh.com/health/live
curl -fsS https://raoza.raadwajeeh.com/health/ready
curl -fsSI https://raoza.raadwajeeh.com/shop
curl -fsSI https://raoza.raadwajeeh.com/build/manifest.json
php "$DOMAIN_ROOT/raoza-app/artisan" schedule:list
php "$DOMAIN_ROOT/raoza-app/artisan" queue:failed
```

Also verify `/`, `/shop`, one product, cart, checkout, `/admin/login`, an uploaded image, HTTPS return/webhook URLs, generated JS/CSS URLs, and a Laravel-rendered 404.

### 6. Code rollback only (when applied migrations remain backward compatible)

```bash
cd "$DOMAIN_ROOT"
php raoza-app/artisan down --retry=60
ln -sfn releases/<previous-release-id> raoza-app
php raoza-app/artisan optimize
php raoza-app/artisan queue:restart
php raoza-app/artisan up
```

Do not automatically roll back migrations. Any incompatible data rollback requires an approved isolated restore and reconciliation of every order/payment created after the backup.
