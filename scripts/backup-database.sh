#!/usr/bin/env bash
#
# Database backup (MySQL) - enterprise safe
#
# - NO hardcoded credentials in repo
# - Reads DB_* from the app .env by default
# - Creates gzipped dumps + sha256
# - Enforces retention
#
# Usage:
#   ./scripts/backup-database.sh [path_to_env]
#
set -euo pipefail

ENV_FILE="${1:-/var/www/rewardstack/.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: env file not found: $ENV_FILE" >&2
  echo "Create it with: cp /var/www/rewardstack/.env.example /var/www/rewardstack/.env" >&2
  exit 1
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" ]]; then
  echo "ERROR: DB_DATABASE and DB_USERNAME must be set in $ENV_FILE" >&2
  exit 1
fi

# Some mysqldump builds (notably MariaDB's) do not support --set-gtid-purged.
# Use it only when available to avoid breaking backups.
MYSQLDUMP_HELP="$(mysqldump --help 2>&1 || true)"
DUMP_GTI_FLAGS=()
if [[ "$MYSQLDUMP_HELP" == *"set-gtid-purged"* ]]; then
  DUMP_GTI_FLAGS+=(--set-gtid-purged=OFF)
fi

BACKUP_DIR="${BACKUP_DIR:-/var/www/rewardstack/storage/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

mkdir -p "$BACKUP_DIR"

TS="$(date +%Y%m%d_%H%M%S)"
OUT_BASE="${DB_DATABASE}_${TS}.sql.gz"
OUT_FILE="$BACKUP_DIR/$OUT_BASE"
SHA_FILE="$OUT_FILE.sha256"

cleanup_failed_backup() {
  unset MYSQL_PWD 2>/dev/null || true
  rm -f "$OUT_FILE" "$SHA_FILE"
}
trap cleanup_failed_backup ERR

echo "Starting backup: $OUT_FILE"

# Use MYSQL_PWD to avoid exposing password in process list
export MYSQL_PWD="$DB_PASSWORD"

mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --quick \
  "${DUMP_GTI_FLAGS[@]}" \
  "$DB_DATABASE" | gzip > "$OUT_FILE"

unset MYSQL_PWD

# Verify backup is non-empty
if [[ ! -s "$OUT_FILE" ]]; then
  echo "ERROR: backup file is empty: $OUT_FILE" >&2
  rm -f "$OUT_FILE"
  exit 1
fi

sha256sum "$OUT_FILE" > "$SHA_FILE"

echo "Backup completed: $OUT_FILE"

du -h "$OUT_FILE" | awk '{print "Backup size: " $1}'

echo "Removing backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +"$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -name "*.sql.gz.sha256" -type f -mtime +"$RETENTION_DAYS" -delete

echo "Current backups:"
ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null || echo "No backups found"
