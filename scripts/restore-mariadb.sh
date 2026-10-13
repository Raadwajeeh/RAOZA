#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 /private/path/raoza-mariadb-TIMESTAMP.sql.gz" >&2
  exit 2
fi

BACKUP="$1"
[[ -f "$BACKUP" ]] || { echo "Backup not found." >&2; exit 1; }
[[ "${ALLOW_DATABASE_RESTORE:-no}" == yes ]] || { echo "Set ALLOW_DATABASE_RESTORE=yes explicitly." >&2; exit 1; }
[[ "${APP_ENV:-}" == restore-verification ]] || { echo "APP_ENV must be restore-verification." >&2; exit 1; }
: "${BACKUP_MYSQL_DEFAULTS_FILE:?Set BACKUP_MYSQL_DEFAULTS_FILE}"
: "${DB_DATABASE:?DB_DATABASE is required as the protected source name}"
: "${RESTORE_DB_DATABASE:?RESTORE_DB_DATABASE is required}"
[[ "$RESTORE_DB_DATABASE" != "$DB_DATABASE" ]] || { echo "Refusing to restore over DB_DATABASE." >&2; exit 1; }
[[ "$RESTORE_DB_DATABASE" == *_restore_verify ]] || { echo "RESTORE_DB_DATABASE must end in _restore_verify." >&2; exit 1; }
[[ -f "$BACKUP_MYSQL_DEFAULTS_FILE" ]] || { echo "MariaDB client file does not exist." >&2; exit 1; }

CLIENT_BIN="$(command -v mariadb || command -v mysql || true)"
[[ -n "$CLIENT_BIN" ]] || { echo "Neither mariadb nor mysql client is available." >&2; exit 1; }
gzip -t "$BACKUP"
if [[ -f "$BACKUP.sha256" ]]; then
  (cd "$(dirname "$BACKUP")" && sha256sum -c "$(basename "$BACKUP").sha256")
else
  echo "A matching checksum file is required." >&2
  exit 1
fi

gzip -cd "$BACKUP" | "$CLIENT_BIN" --defaults-extra-file="$BACKUP_MYSQL_DEFAULTS_FILE" --database="$RESTORE_DB_DATABASE"

echo "Restore completed into the isolated verification database. Run migrations/status and application smoke checks before recording success."
