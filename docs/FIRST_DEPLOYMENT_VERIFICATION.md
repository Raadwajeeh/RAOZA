# First Hostinger deployment verification

This review covers the first conversion of the existing staging site at `raoza.raadwajeeh.com` from ordinary `raoza-app` and `public_html` directories to the managed release/shared-storage layout. It is a local repository review only. No Hostinger account, staging database, DNS, Apache configuration, or live service was accessed.

The executable procedure is [HOSTINGER_DEPLOYMENT](HOSTINGER_DEPLOYMENT.md). This document records why each first-deployment gate exists and which checks still require Hostinger.

## Confirmed risks and fixes

| Confirmed risk | Implemented local fix |
|---|---|
| A broad or mistyped `DOMAIN_ROOT` could target `raadwajeeh.com` instead of the RAOZA subdomain. | The runbook and `deploy-hostinger.sh` canonicalize the path and require the exact `domains/raoza.raadwajeeh.com` basename pair. The script was locally exercised against a wrong parent path and refused it. |
| The first conversion previously moved live directories without a complete, checksum-verified filesystem backup or a stepwise recovery path. | The procedure now enters maintenance mode, archives both existing directories privately, verifies gzip and SHA-256, requires the MariaDB dump/checksum, prepares replacement paths before cutover, and retains timestamped originals. |
| Existing uploads may be in `raoza-app/storage/app/public`, a real `public_html/storage`, or both. A blind copy can lose or overwrite media. | Both locations are inventoried. The application storage is copied with metadata; a second real web-root store is copied only when the first is empty, otherwise `diff -qr` must succeed and conflicts stop the deployment. |
| Copying a template `.env` could rotate `APP_KEY`, lose database credentials, or expose secrets. | The current `.env` is installed to `shared/.env` with mode 600. Operators must edit that preserved file, retain `APP_KEY` and database credentials, and use the template only as a checklist. Subsequent deployment rejects a symlinked or overly permissive shared `.env`. |
| Shell symlink creation does not prove Hostinger Apache follows links or allows the `.htaccess` directives. | A harmless shell link probe is required before cutover; Apache link/rewrite behavior remains a mandatory HTTP acceptance check with directory-name rollback on 403/500. |
| Laravel's internal public directory, the Vite manifest, and `public_html` could point to different releases. | Laravel retains `<release>/public`; Vite is verified at `<release>/public/build`; `public_html/build` targets `../raoza-app/public/build`. The front controller now refuses an application outside the managed `releases` directory. |
| The web-root map omitted the tracked `public/demo` directory, so an existing legacy demo catalog could reference missing images during staging review. | `public_html/demo` is now a managed versioned symlink and is validated by subsequent deployments. Demo payment mode and demo seeders remain forbidden. |
| The release builder installed `--no-dev` dependencies before running PHPUnit, which can remove the test runner. | It now installs development dependencies for verification, runs typecheck/build/build-verification/PHPUnit, then performs a final optimized `--no-dev --classmap-authoritative` install. It also rejects `.env`, checks required deployment/build files, and removes test cache output. |
| Release ZIP extraction accepted uninspected entry paths. | First and subsequent procedures list the archive and reject absolute, parent-traversal, or backslash paths before extraction. |
| The later-release script accepted arbitrary managed symlinks and a normal `raoza-app` directory. | It now requires the managed application link, exact public symlink targets, a valid active release, protected `.env`, and an atomic temporary-link switch. It explicitly refuses use as the first-conversion tool. |
| A failed MariaDB DDL migration may not be transactionally reversible, making automatic code/database rollback unsafe. | Automatic migration rollback is forbidden. An isolated restore rehearsal is required first. After any failed/applied migration, the site stays down and recovery restores into a new database for validation and approved connection cutover. |
| A readiness gate could accidentally require Mollie Live or a real order. | Staging uses `production:preflight` without `--require-live-services`, a Mollie test key, `/health/live`, `/health/ready`, asset/page checks, and queue/scheduler inspection. The preflight command does not contact Mollie or SMTP. |
| Catalog initialization could destroy data or introduce demo credentials/content. | The procedure forbids `migrate:fresh`, `db:wipe`, migration rollback, and all demo/legacy seeders. `production:bootstrap` is allowed only after confirming the staging catalog is empty and its private manifest is reviewed. |

## Exact prerequisites

Do not start the maintenance window until all of these are evidenced:

1. A reviewed commit builds from a clean worktree using:

   ```powershell
   git status --short
   powershell -ExecutionPolicy Bypass -File scripts/build-hostinger-package.ps1
   ```

   The output ZIP and `.sha256` must be uploaded to a private path outside `public_html`.

2. A Hostinger account snapshot exists and its restore mechanism is understood.
3. The exact root is `/home/ACCOUNT/domains/raoza.raadwajeeh.com`; both `raoza-app` and `public_html` are ordinary directories, not managed links.
4. There is enough free space for both existing directories, their compressed archive, a MariaDB dump, and an extracted release.
5. Hostinger provides the intended PHP 8.4 CLI and required PHP extensions. Bash 4+, Composer, `tar`, `gzip`, `unzip`, `sha256sum`, `readlink`, and GNU `mv -T` are available.
6. A mode-600 MariaDB client defaults file and private backup directory exist. The application can enter maintenance mode.
7. An isolated MariaDB database whose name ends in `_restore_verify` is available, and the pre-deployment dump/media restore drill has succeeded there.
8. The existing `.env` and `APP_KEY` are available. Staging uses exact HTTPS URLs, a Mollie test key, non-demo mode, SMTP staging settings, database sessions/cache/queue, and persistent local storage.
9. Owner-reviewed catalog values are available only if the existing staging catalog is confirmed empty. No catalog bootstrap is run merely because the shop currently appears empty.
10. A maintenance window and an operator authorized to keep the site down on migration failure are available.

## Safe first-deployment steps

Use the complete command blocks in [HOSTINGER_DEPLOYMENT](HOSTINGER_DEPLOYMENT.md), in order. The required control flow is:

1. Canonicalize `DOMAIN_ROOT`; require the exact subdomain and `domains` parent; inventory both trees, permissions, `.env`, and both potential media locations.
2. Run the harmless symlink probe. Do not infer Apache support from this shell-only check.
3. Enter Laravel maintenance mode before taking backups.
4. Create and verify `backups/raoza-files-<UTC timestamp>.tar.gz` containing the original `raoza-app` and `public_html`.
5. Create the MariaDB dump with `scripts/backup-mariadb.sh`; require a zero exit, non-empty gzip, and valid SHA-256. Complete the isolated restore drill before structural or database migration.
6. Create a new mode-700 `shared` directory only if none exists. Preserve `.env` as mode 600 and copy the old storage tree with metadata. Reconcile a real `public_html/storage` without overwriting conflicts.
7. Verify the release checksum and archive entry paths, extract into a new immutable release ID, attach `shared/storage` and `shared/.env`, then run:

   ```bash
   composer validate --strict
   composer check-platform-reqs --no-dev
   php artisan production:verify-build
   php artisan production:preflight
   php artisan migrate:status
   php artisan schedule:list
   ```

8. Prepare `public_html.next-<release-id>` and `raoza-app.next-<release-id>` completely. Verify all link text before touching the current names.
9. While the old application remains down, rename the old directories to timestamped `*.pre-managed-*` names and move the prepared link/root into the canonical names.
10. Run only forward migrations, inspect their status before and after, optimize, restart queued workers logically, and leave maintenance mode:

    ```bash
    cd "$DOMAIN_ROOT/raoza-app"
    php artisan migrate:status
    php artisan migrate --force
    php artisan migrate:status
    php artisan optimize
    php artisan queue:restart
    php artisan up
    ```

11. Run non-transactional acceptance checks:

    ```bash
    curl -fsS https://raoza.raadwajeeh.com/health/live
    curl -fsS https://raoza.raadwajeeh.com/health/ready
    curl -fsSI https://raoza.raadwajeeh.com/shop
    php "$DOMAIN_ROOT/raoza-app/artisan" schedule:list
    php "$DOMAIN_ROOT/raoza-app/artisan" queue:failed
    ```

    Also verify `/`, one product, cart rendering without submission, checkout rendering without placing an order, `/admin/login`, emitted JS/CSS URLs, an existing uploaded image, and a Laravel-rendered `/nonexistent` 404. Do not make a live payment or real customer order.

12. Retain the snapshot, filesystem archive, database backup, media inventory, old directories, and failed/new release until acceptance and a rollback rehearsal are recorded.

## Rollback procedure

Before any database migration is applied, restore directory names and bring up the preserved old application:

```bash
cd "$DOMAIN_ROOT"
test -d "$OLD_APP" && test -d "$OLD_PUBLIC"
test -L raoza-app && mv -- raoza-app "raoza-app.failed-$STAMP"
test -d public_html && mv -- public_html "public_html.failed-$STAMP"
mv -- "$OLD_APP" raoza-app
mv -- "$OLD_PUBLIC" public_html
php "$DOMAIN_ROOT/raoza-app/artisan" up
```

If any migration was applied or failed midway:

- keep staging in maintenance mode;
- preserve the failed database and logs as evidence;
- do not run `migrate:rollback` and do not restore over that database;
- restore the verified dump and media into a newly created isolated database/environment;
- validate migrations/status, table counts, commerce records, inventory, admin access, and sampled images;
- approve the database connection change and directory rollback only after validation;
- use code-only rollback with the forward schema only when every applied migration has separately been proven backward compatible.

## Outstanding Hostinger checks

None of the following was executed locally and none is claimed as passed:

- the exact Hostinger domain path, current directory types, free space, ownership, ACLs, and PHP/web-server users;
- Hostinger snapshot creation/restoration;
- symbolic-link creation and Apache `FollowSymLinks` behavior;
- whether `Options -MultiViews -Indexes`, `DirectoryIndex`, `mod_rewrite`, authorization headers, and `.htaccess` overrides are accepted without 403/500;
- PHP 8.4 path/extensions, Composer availability, GNU `mv -T`, MariaDB client/server versions, TLS/CA configuration, UTC, InnoDB, `READ COMMITTED`, and account privileges;
- current `.env`, `APP_KEY`, media locations/counts/conflicts, existing database contents, or catalog state;
- creation and restoration of the filesystem archive, MariaDB dump, checksum, Hostinger snapshot, or isolated restore database;
- scheduler/queue cron paths, non-overlap, write permissions, failed-job handling, and alert delivery;
- SMTP delivery/DNS, Mollie test webhook/return flow, proxy/TLS headers, Apache 404 behavior, or HTTP asset/media checks;
- an end-to-end clean-worktree run of `build-hostinger-package.ps1` or execution of `deploy-hostinger.sh` against a managed Hostinger layout.

## Local verification results

Executed on 2026-10-13:

| Check actually executed | Result |
|---|---|
| `composer validate --strict` | Passed |
| `php artisan test` | Passed: 160 tests, 869 assertions |
| `npm run typecheck` | Passed |
| `npm run build` | Passed: Vite transformed 816 modules |
| `php artisan production:verify-build` | Passed: 45 manifest entries, 46 files |
| `composer audit --locked --no-interaction` | Passed: no security advisories |
| `npm audit --omit=dev` | Passed: 0 vulnerabilities |
| Git Bash `bash -n` on five deployment/backup/release shell scripts | Passed |
| `php -l deployment/hostinger/public_html/index.php` | Passed |
| Front controller without a managed release | Passed: returned the fail-closed `Application release is unavailable.` response |
| PowerShell parser on `build-hostinger-package.ps1` | Passed |
| `deploy-hostinger.sh` invoked with a real but wrong parent path | Passed: exited 1 with the exact-domain guard before inspecting archive/backup inputs |
| Release builder invoked from the current dirty review worktree | Passed its guard: refused to build because the worktree is not clean |
| `git diff --check` | Passed |
| Pint on the changed Hostinger PHP front controller | Passed |
| Canonical public-entry coverage against the managed web-root map | Passed for `brand`, `build`, `campaign`, `catalog`, `demo`, and `favicon.svg` |

An earlier combined parallel verification invocation exceeded its 120-second tool window and produced no reliable per-command results. Every result reported above comes from the subsequent individually completed commands. No MariaDB integration service, Apache server, Hostinger filesystem, or external service was exercised.
