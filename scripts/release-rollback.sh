#!/usr/bin/env bash
#
# Roll back current -> previous release.
#
set -euo pipefail

RELEASE_BASE="${RELEASE_BASE:-}"
if [[ -z "$RELEASE_BASE" ]]; then
  echo "ERROR: RELEASE_BASE is required (e.g. /var/www/rewardstack-app)" >&2
  exit 1
fi

RELEASES_DIR="$RELEASE_BASE/releases"
CURRENT_LINK="$RELEASE_BASE/current"

if [[ ! -d "$RELEASES_DIR" ]]; then
  echo "ERROR: releases dir missing: $RELEASES_DIR" >&2
  exit 1
fi

# Find latest and previous release
mapfile -t rels < <(ls -1dt "$RELEASES_DIR"/* 2>/dev/null || true)
if [[ ${#rels[@]} -lt 2 ]]; then
  echo "ERROR: need at least 2 releases to rollback" >&2
  exit 1
fi

prev="${rels[1]}"

echo "==> Rolling back to: $prev"
ln -sfn "$prev" "$CURRENT_LINK"

echo "==> Restarting queue workers (Supervisor if present)"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart qrrevenue-worker:* || true
fi

echo "==> Optional: reload PHP-FPM"
if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || true
  systemctl reload php8.2-fpm 2>/dev/null || true
  systemctl reload php8.1-fpm 2>/dev/null || true
fi

echo "Rollback complete."
