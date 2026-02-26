#!/usr/bin/env bash
#
# Verify a backup file is readable and (optionally) checksum-valid.
#
# Usage:
#   ./scripts/verify-backup.sh /path/to/backup.sql.gz
#
set -euo pipefail

BACKUP_FILE="${1:-}"
if [[ -z "$BACKUP_FILE" || ! -f "$BACKUP_FILE" ]]; then
  echo "ERROR: backup file required" >&2
  exit 1
fi

if [[ -f "$BACKUP_FILE.sha256" ]]; then
  echo "Verifying sha256..."
  (cd "$(dirname "$BACKUP_FILE")" && sha256sum -c "$(basename "$BACKUP_FILE.sha256")")
fi

echo "Verifying gzip stream..."
gzip -t "$BACKUP_FILE"

echo "OK: backup looks valid"
