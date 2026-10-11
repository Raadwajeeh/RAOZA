#!/usr/bin/env bash
set -Eeuo pipefail

# Run from a checked-out release directory after CI has passed.
# Secrets must already exist in .env or the host secret manager.
# This script never fetches code, changes DNS, provisions infrastructure or creates secrets.

: "${DEPLOY_CONFIRM_SHA:?Set DEPLOY_CONFIRM_SHA to the reviewed full Git SHA}"
: "${VERIFIED_BACKUP_FILE:?Set VERIFIED_BACKUP_FILE to the pre-migration custom-format PostgreSQL dump}"

CURRENT_SHA="$(git rev-parse HEAD)"
[[ "$DEPLOY_CONFIRM_SHA" == "$CURRENT_SHA" ]] || { echo "DEPLOY_CONFIRM_SHA does not match HEAD ($CURRENT_SHA)." >&2; exit 1; }
[[ -z "$(git status --porcelain)" ]] || { echo "Release working tree is not clean." >&2; exit 1; }
[[ -f "$VERIFIED_BACKUP_FILE" ]] || { echo "Verified backup does not exist: $VERIFIED_BACKUP_FILE" >&2; exit 1; }
[[ -f "$VERIFIED_BACKUP_FILE.sha256" ]] || { echo "Backup checksum is missing: $VERIFIED_BACKUP_FILE.sha256" >&2; exit 1; }
(cd "$(dirname "$VERIFIED_BACKUP_FILE")" && sha256sum -c "$(basename "$VERIFIED_BACKUP_FILE").sha256")
pg_restore --list "$VERIFIED_BACKUP_FILE" >/dev/null

[[ "${APP_ENV:-production}" == "production" ]] || echo "APP_ENV shell variable is not production; Laravel .env remains authoritative."

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run typecheck
npm run build

php artisan about --only=environment
PREFLIGHT_OPTIONS=()
if [[ "${DEPLOY_REQUIRE_LIVE_SERVICES:-no}" == "yes" ]]; then
  PREFLIGHT_OPTIONS+=(--require-live-services)
fi
php artisan production:preflight "${PREFLIGHT_OPTIONS[@]}"
php artisan migrate:status
php artisan schedule:list

php artisan down --retry=60 --refresh=15 || true
trap 'php artisan up || true' EXIT

php artisan migrate --force
php artisan optimize
php artisan storage:link --force
php artisan queue:restart

php artisan up
trap - EXIT

echo "Application steps complete. Keep rollback release available and verify health, worker, scheduler and smoke checks before declaring success."
