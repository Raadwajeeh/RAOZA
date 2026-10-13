#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 2 ]]; then
  echo "Usage: $0 /private/path/raoza-SHA.zip RELEASE_ID" >&2
  exit 2
fi

ARCHIVE="$1"
RELEASE_ID="$2"
: "${DOMAIN_ROOT:?Set DOMAIN_ROOT to the absolute raoza.raadwajeeh.com domain directory}"
: "${VERIFIED_BACKUP_FILE:?Set VERIFIED_BACKUP_FILE to the reviewed pre-deployment .sql.gz backup}"

[[ "$DOMAIN_ROOT" = /* && "$DOMAIN_ROOT" != / ]] || { echo "DOMAIN_ROOT must be an absolute, non-root path." >&2; exit 1; }
[[ -d "$DOMAIN_ROOT" ]] || { echo "DOMAIN_ROOT does not exist." >&2; exit 1; }
DOMAIN_ROOT="$(cd "$DOMAIN_ROOT" && pwd -P)"
[[ "$(basename "$DOMAIN_ROOT")" == raoza.raadwajeeh.com && "$(basename "$(dirname "$DOMAIN_ROOT")")" == domains ]] || {
  echo "DOMAIN_ROOT must be the domains/raoza.raadwajeeh.com directory, never the parent domain." >&2
  exit 1
}
[[ "$ARCHIVE" = /* && "$VERIFIED_BACKUP_FILE" = /* ]] || { echo "Archive and backup paths must be absolute." >&2; exit 1; }
[[ "$RELEASE_ID" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ && "$RELEASE_ID" != . && "$RELEASE_ID" != .. ]] || { echo "RELEASE_ID contains unsafe characters." >&2; exit 1; }
[[ -f "$ARCHIVE" && -f "$ARCHIVE.sha256" ]] || { echo "Release archive or checksum is missing." >&2; exit 1; }
[[ -f "$VERIFIED_BACKUP_FILE" && -f "$VERIFIED_BACKUP_FILE.sha256" ]] || { echo "Backup or checksum is missing." >&2; exit 1; }

command -v unzip >/dev/null
command -v sha256sum >/dev/null
ARCHIVE_LIST="$(unzip -Z1 "$ARCHIVE")" || { echo "Release archive cannot be listed." >&2; exit 1; }
grep -Eq '(^/|(^|/)\.\.(/|$)|\\)' <<< "$ARCHIVE_LIST" && { echo "Release archive contains an unsafe path." >&2; exit 1; }
(cd "$(dirname "$ARCHIVE")" && sha256sum -c "$(basename "$ARCHIVE").sha256")
(cd "$(dirname "$VERIFIED_BACKUP_FILE")" && sha256sum -c "$(basename "$VERIFIED_BACKUP_FILE").sha256")
gzip -t "$VERIFIED_BACKUP_FILE"

RELEASES="$DOMAIN_ROOT/releases"
SHARED="$DOMAIN_ROOT/shared"
PUBLIC="$DOMAIN_ROOT/public_html"
APP_LINK="$DOMAIN_ROOT/raoza-app"
RELEASE="$RELEASES/$RELEASE_ID"

[[ ! -e "$RELEASE" ]] || { echo "Release already exists: $RELEASE" >&2; exit 1; }
[[ -f "$SHARED/.env" && ! -L "$SHARED/.env" ]] || { echo "Missing protected regular shared/.env." >&2; exit 1; }
ENV_MODE="$(stat -c '%a' "$SHARED/.env")"
[[ "$ENV_MODE" == 600 || "$ENV_MODE" == 400 ]] || { echo "shared/.env permissions must be 600 or 400." >&2; exit 1; }
case "$ARCHIVE" in "$PUBLIC"|"$PUBLIC"/*) echo "Release archive must be outside public_html." >&2; exit 1 ;; esac
case "$VERIFIED_BACKUP_FILE" in "$PUBLIC"|"$PUBLIC"/*) echo "Database backup must be outside public_html." >&2; exit 1 ;; esac
[[ -f "$PUBLIC/index.php" && -f "$PUBLIC/.htaccess" ]] || { echo "Hostinger public_html templates are not installed." >&2; exit 1; }
[[ -L "$APP_LINK" ]] || { echo "raoza-app must already be a managed release symlink. Use the first-deployment procedure first." >&2; exit 1; }
OLD_TARGET="$(readlink "$APP_LINK")"
[[ "$OLD_TARGET" =~ ^releases/[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || { echo "raoza-app points outside the managed releases directory." >&2; exit 1; }
declare -A PUBLIC_TARGETS=(
  [build]="../raoza-app/public/build"
  [brand]="../raoza-app/public/brand"
  [campaign]="../raoza-app/public/campaign"
  [catalog]="../raoza-app/public/catalog"
  [demo]="../raoza-app/public/demo"
  [favicon.svg]="../raoza-app/public/favicon.svg"
  [storage]="../shared/storage/app/public"
)
for entry in "${!PUBLIC_TARGETS[@]}"; do
  [[ -L "$PUBLIC/$entry" && "$(readlink "$PUBLIC/$entry")" == "${PUBLIC_TARGETS[$entry]}" ]] || {
    echo "public_html/$entry is not the expected managed symbolic link." >&2
    exit 1
  }
done
[[ -f "$DOMAIN_ROOT/$OLD_TARGET/artisan" ]] || { echo "The active release target is invalid." >&2; exit 1; }

mkdir -p -- "$RELEASES" "$SHARED"
mkdir -- "$RELEASE"
unzip -q "$ARCHIVE" -d "$RELEASE"
[[ -f "$RELEASE/artisan" && -f "$RELEASE/public/build/manifest.json" && -f "$RELEASE/vendor/autoload.php" ]] || { echo "Release archive is incomplete." >&2; exit 1; }

if [[ ! -d "$SHARED/storage" ]]; then
  mv -- "$RELEASE/storage" "$SHARED/storage"
else
  rm -rf -- "$RELEASE/storage"
fi
ln -s ../../shared/storage "$RELEASE/storage"
ln -s ../../shared/.env "$RELEASE/.env"
chmod -R u+rwX "$SHARED/storage" "$RELEASE/bootstrap/cache"

cd "$RELEASE"
composer validate --strict
composer check-platform-reqs --no-dev
php artisan production:verify-build
php artisan production:preflight
php artisan migrate:status
php artisan schedule:list

if [[ -L "$APP_LINK" ]]; then
  php "$APP_LINK/artisan" down --retry=60 --refresh=15 || true
fi
NEXT_LINK="$DOMAIN_ROOT/.raoza-app.next.$$"
SWITCHED=no
cleanup() {
  status=$?
  rm -f -- "$NEXT_LINK"
  if [[ "$status" -ne 0 ]]; then
    if [[ "$SWITCHED" == yes ]]; then
      echo "Deployment failed after the code switch. The active link remains on the new release; follow the documented migration-aware rollback procedure." >&2
    fi
    [[ -L "$APP_LINK" ]] && php "$APP_LINK/artisan" up || true
  fi
  exit "$status"
}
trap cleanup EXIT

php artisan migrate --force
php artisan optimize

ln -s "releases/$RELEASE_ID" "$NEXT_LINK"
mv -Tf -- "$NEXT_LINK" "$APP_LINK"
SWITCHED=yes
[[ "$(readlink "$APP_LINK")" == "releases/$RELEASE_ID" ]] || { echo "Active release switch verification failed." >&2; exit 1; }
php "$APP_LINK/artisan" queue:restart
php "$APP_LINK/artisan" up
trap - EXIT

echo "Release activated. Run HTTPS health, asset, shop, admin-login and scheduler/queue smoke checks before acceptance."
