#!/usr/bin/env bash
set -Eeuo pipefail

command -v php >/dev/null
command -v composer >/dev/null
command -v npm >/dev/null

if [[ ! -f .env ]]; then
  echo "Missing .env" >&2
  exit 1
fi

php artisan about --only=environment >/dev/null
php artisan config:clear
composer validate --strict
composer install --no-interaction --prefer-dist
npm ci --no-audit --no-fund
npm run typecheck
npm run build
php artisan test
php artisan migrate:status >/dev/null
php artisan schedule:list | grep -q 'commerce:reconcile'

echo "RAOZA release checks passed."
