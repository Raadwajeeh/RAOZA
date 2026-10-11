#!/usr/bin/env bash
set -Eeuo pipefail

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

: "${BACKUP_DIR:?BACKUP_DIR must be an absolute directory outside the application release}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] || { echo "BACKUP_RETENTION_DAYS must be a non-negative integer." >&2; exit 1; }
[[ "$BACKUP_DIR" = /* ]] || { echo "BACKUP_DIR must be absolute." >&2; exit 1; }
case "$BACKUP_DIR" in
  "$PWD"|"$PWD"/*) echo "BACKUP_DIR must be outside the application release." >&2; exit 1 ;;
esac
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="$BACKUP_DIR/raoza-${STAMP}.dump"

export PGPASSWORD="$DB_PASSWORD"
export PGSSLMODE="${DB_SSLMODE:-require}"
[[ -z "${DB_SSLROOTCERT:-}" ]] || export PGSSLROOTCERT="$DB_SSLROOTCERT"
[[ -z "${DB_SSLCERT:-}" ]] || export PGSSLCERT="$DB_SSLCERT"
[[ -z "${DB_SSLKEY:-}" ]] || export PGSSLKEY="$DB_SSLKEY"
pg_dump --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --format=custom --no-owner --no-acl --file="$FILE" "$DB_DATABASE"
unset PGPASSWORD PGSSLMODE PGSSLROOTCERT PGSSLCERT PGSSLKEY
(cd "$BACKUP_DIR" && sha256sum "$(basename "$FILE")" > "$(basename "$FILE").sha256")
(cd "$BACKUP_DIR" && sha256sum -c "$(basename "$FILE").sha256")
pg_restore --list "$FILE" >/dev/null
find "$BACKUP_DIR" -type f \( -name 'raoza-*.dump' -o -name 'raoza-*.dump.sha256' \) -mtime "+$RETENTION_DAYS" -delete

echo "Backup archive checksum and table-of-contents validation passed. An isolated restore drill is still required."
echo "$FILE"
