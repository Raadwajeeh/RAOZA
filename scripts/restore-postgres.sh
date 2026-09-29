#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 path/to/raoza-backup.dump" >&2
  exit 2
fi

BACKUP="$1"
[[ -f "$BACKUP" ]] || { echo "Backup not found: $BACKUP" >&2; exit 1; }
[[ "${ALLOW_DATABASE_RESTORE:-no}" == "yes" ]] || { echo "Set ALLOW_DATABASE_RESTORE=yes explicitly." >&2; exit 1; }
[[ "${APP_ENV:-}" == "restore-verification" ]] || { echo "Set APP_ENV=restore-verification; restores are refused in normal application environments." >&2; exit 1; }

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required as the source database name safety check}"
: "${RESTORE_DB_DATABASE:?RESTORE_DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
[[ "$RESTORE_DB_DATABASE" != "$DB_DATABASE" ]] || { echo "Refusing to restore over DB_DATABASE." >&2; exit 1; }
[[ "$RESTORE_DB_DATABASE" == *_restore_verify ]] || { echo "RESTORE_DB_DATABASE must end in _restore_verify." >&2; exit 1; }

if [[ -f "$BACKUP.sha256" ]]; then
  (cd "$(dirname "$BACKUP")" && sha256sum -c "$(basename "$BACKUP").sha256")
fi
export PGPASSWORD="$DB_PASSWORD"
pg_restore --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --dbname="$RESTORE_DB_DATABASE" --clean --if-exists --no-owner --no-acl "$BACKUP"
unset PGPASSWORD

echo "Restore completed. Run application smoke tests before reopening traffic."
