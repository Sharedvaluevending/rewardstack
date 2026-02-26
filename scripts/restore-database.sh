#!/usr/bin/env bash
#
# Restore a MySQL backup created by backup-database.sh
#
# Usage:
#   ./scripts/restore-database.sh /path/to/backup.sql.gz [path_to_env]
#
set -euo pipefail

BACKUP_FILE="${1:-}"
ENV_FILE="${2:-/var/www/rewardstack/.env}"

if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
  echo "ERROR: backup file required: /path/to/*.sql.gz" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: env file not found: $ENV_FILE" >&2
  exit 1
fi

# Verify checksum if present
if [[ -f "$BACKUP_FILE.sha256" ]]; then
  echo "Verifying sha256..."
  (cd "$(dirname "$BACKUP_FILE")" && sha256sum -c "$(basename "$BACKUP_FILE.sha256")")
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

echo "Restoring into database: $DB_DATABASE on $DB_HOST:$DB_PORT"

export MYSQL_PWD="$DB_PASSWORD"

gzip -dc "$BACKUP_FILE" | mysql   --host="$DB_HOST"   --port="$DB_PORT"   --user="$DB_USERNAME"   "$DB_DATABASE"

unset MYSQL_PWD

echo "Restore complete."
