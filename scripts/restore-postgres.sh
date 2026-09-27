#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 path/to/raoza-backup.dump" >&2
  exit 2
fi

BACKUP="$1"
[[ -f "$BACKUP" ]] || { echo "Backup not found: $BACKUP" >&2; exit 1; }
[[ "${ALLOW_DATABASE_RESTORE:-no}" == "yes" ]] || { echo "Set ALLOW_DATABASE_RESTORE=yes explicitly." >&2; exit 1; }

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

if [[ -f "$BACKUP.sha256" ]]; then sha256sum -c "$BACKUP.sha256"; fi
export PGPASSWORD="$DB_PASSWORD"
pg_restore --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --dbname="$DB_DATABASE" --clean --if-exists --no-owner --no-acl "$BACKUP"
unset PGPASSWORD

echo "Restore completed. Run application smoke tests before reopening traffic."
