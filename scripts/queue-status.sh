#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/rewardstack}"
cd "$APP_DIR"

echo "== Failed jobs =="
php artisan queue:failed || true

echo ""
echo "== Queue sizes (requires redis-cli) =="
if command -v redis-cli >/dev/null 2>&1; then
  QH="${QUEUE_HIGH:-high}"
  QD="${QUEUE_DEFAULT:-default}"
  QL="${QUEUE_LOW:-low}"
  # Laravel uses lists: queues:<name>
  for q in "$QH" "$QD" "$QL"; do
    key="queues:$q"
    size=$(redis-cli LLEN "$key" 2>/dev/null || echo "?")
    echo "$key -> $size"
  done
else
  echo "redis-cli not installed"
fi
