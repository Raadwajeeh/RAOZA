#!/usr/bin/env bash
set -Eeuo pipefail

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

BACKUP_DIR="${BACKUP_DIR:-storage/app/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="$BACKUP_DIR/raoza-${STAMP}.dump"

export PGPASSWORD="$DB_PASSWORD"
pg_dump --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" --format=custom --no-owner --no-acl --file="$FILE" "$DB_DATABASE"
unset PGPASSWORD
sha256sum "$FILE" > "$FILE.sha256"
find "$BACKUP_DIR" -type f \( -name 'raoza-*.dump' -o -name 'raoza-*.dump.sha256' \) -mtime "+$RETENTION_DAYS" -delete

echo "$FILE"
