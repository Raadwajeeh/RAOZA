#!/usr/bin/env bash
set -Eeuo pipefail

# Run from a checked-out release directory after CI has passed.
# Secrets must already exist in .env or the host secret manager.

[[ "${APP_ENV:-production}" == "production" ]] || echo "APP_ENV shell variable is not production; Laravel .env remains authoritative."

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

php artisan down --retry=60 --refresh=15 || true
trap 'php artisan up || true' EXIT

php artisan migrate --force
php artisan optimize
php artisan storage:link --force
php artisan queue:restart

php artisan up
trap - EXIT

echo "Deploy complete. Verify /health/ready and worker status before declaring the release healthy."
