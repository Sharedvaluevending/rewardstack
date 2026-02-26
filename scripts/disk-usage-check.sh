#!/usr/bin/env bash
#
# Lightweight disk usage check (logs only).
# Writes to storage/logs so existing logrotate rules apply.
#
set -euo pipefail

LOG_FILE="/var/www/rewardstack/storage/logs/disk-usage.log"
BACKUP_DIR="/var/www/rewardstack/storage/backups"

ts="$(date -Iseconds)"

# Filesystem usage for /
df_line="$(df -P / | awk 'NR==2 {print $2, $3, $4, $5}')"
fs_total_kb="$(awk '{print $1}' <<<"$df_line")"
fs_used_kb="$(awk '{print $2}' <<<"$df_line")"
fs_avail_kb="$(awk '{print $3}' <<<"$df_line")"
fs_use_pct="$(awk '{print $4}' <<<"$df_line")"

# Backup dir size (best-effort)
backup_size="$(du -sh "$BACKUP_DIR" 2>/dev/null | awk '{print $1}' || echo "n/a")"

mkdir -p "$(dirname "$LOG_FILE")"
printf '%s disk_check fs_total_kb=%s fs_used_kb=%s fs_avail_kb=%s fs_use_pct=%s backups_size=%s\n' \
  "$ts" "$fs_total_kb" "$fs_used_kb" "$fs_avail_kb" "$fs_use_pct" "$backup_size" >> "$LOG_FILE"

