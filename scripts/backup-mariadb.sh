#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

: "${BACKUP_MYSQL_DEFAULTS_FILE:?Set BACKUP_MYSQL_DEFAULTS_FILE to a protected MariaDB client file}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"

RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
[[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] || { echo "BACKUP_RETENTION_DAYS must be a non-negative integer." >&2; exit 1; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_-]+$ ]] || { echo "DB_DATABASE contains unsupported characters." >&2; exit 1; }
[[ "$BACKUP_DIR" = /* && "$BACKUP_DIR" != / ]] || { echo "BACKUP_DIR must be an absolute, non-root path." >&2; exit 1; }
[[ -f "$BACKUP_MYSQL_DEFAULTS_FILE" ]] || { echo "MariaDB client file does not exist." >&2; exit 1; }
DEFAULTS_MODE="$(stat -c '%a' "$BACKUP_MYSQL_DEFAULTS_FILE")"
[[ "$DEFAULTS_MODE" == 600 || "$DEFAULTS_MODE" == 400 ]] || { echo "MariaDB client file permissions must be 600 or 400." >&2; exit 1; }
case "$BACKUP_DIR" in
  */public_html|*/public_html/*) echo "BACKUP_DIR must be outside public_html." >&2; exit 1 ;;
esac

DUMP_BIN="$(command -v mariadb-dump || command -v mysqldump || true)"
[[ -n "$DUMP_BIN" ]] || { echo "Neither mariadb-dump nor mysqldump is available." >&2; exit 1; }
command -v gzip >/dev/null
command -v sha256sum >/dev/null

mkdir -p -- "$BACKUP_DIR"
chmod 700 -- "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FINAL="$BACKUP_DIR/raoza-mariadb-${STAMP}.sql.gz"
PART="${FINAL}.part"
VERIFY="${FINAL}.verify.sql"
trap 'rm -f -- "$PART" "$VERIFY"' EXIT

"$DUMP_BIN" --defaults-extra-file="$BACKUP_MYSQL_DEFAULTS_FILE" \
  --single-transaction --quick --routines --events --triggers --hex-blob \
  --default-character-set=utf8mb4 --skip-lock-tables "$DB_DATABASE" | gzip -9 > "$PART"

[[ -s "$PART" ]] || { echo "Backup output is empty." >&2; exit 1; }
gzip -t "$PART"
gzip -cd "$PART" > "$VERIFY"
grep -Eq '^(-- MariaDB dump|-- MySQL dump|CREATE TABLE)' "$VERIFY" || { echo "Backup does not look like a SQL dump." >&2; exit 1; }
rm -f -- "$VERIFY"
mv -- "$PART" "$FINAL"
(cd "$BACKUP_DIR" && sha256sum "$(basename "$FINAL")" > "$(basename "$FINAL").sha256")
(cd "$BACKUP_DIR" && sha256sum -c "$(basename "$FINAL").sha256")

find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'raoza-mariadb-*.sql.gz' -o -name 'raoza-mariadb-*.sql.gz.sha256' \) -mtime "+$RETENTION_DAYS" -delete

echo "Backup dump, gzip stream and checksum validation passed. This is not a restore test."
echo "$FINAL"
