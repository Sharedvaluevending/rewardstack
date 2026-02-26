#!/usr/bin/env bash
#
# Release-based deploy with atomic symlink swap.
#
# Requirements on server:
# - git, composer, npm, php
# - RELEASE_BASE directory writable by deploy user
#
# Env vars:
# - RELEASE_BASE (required): base directory holding releases/shared/current
# - REPO_URL (optional): git remote url (if you want to clone/fetch here)
# - APP_URL (optional): for health check
# - KEEP_RELEASES (optional, default 5)
#
set -euo pipefail

RELEASE_BASE="${RELEASE_BASE:-}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

if [[ -z "$RELEASE_BASE" ]]; then
  echo "ERROR: RELEASE_BASE is required (e.g. /var/www/rewardstack-app)" >&2
  exit 1
fi

RELEASES_DIR="$RELEASE_BASE/releases"
SHARED_DIR="$RELEASE_BASE/shared"
CURRENT_LINK="$RELEASE_BASE/current"

mkdir -p "$RELEASES_DIR" "$SHARED_DIR"
mkdir -p "$SHARED_DIR/storage"

# Use timestamp release names
REL_NAME="$(date +%Y%m%d_%H%M%S)"
REL_DIR="$RELEASES_DIR/$REL_NAME"

# Determine source: prefer deploying current repo checkout
SRC_DIR="${SRC_DIR:-/var/www/rewardstack}"

if [[ ! -d "$SRC_DIR/.git" ]]; then
  echo "ERROR: SRC_DIR does not look like a git checkout: $SRC_DIR" >&2
  echo "Set SRC_DIR to your repo directory." >&2
  exit 1
fi

echo "==> Creating release: $REL_DIR"
mkdir -p "$REL_DIR"

# Copy working tree to release dir (fast + predictable)
# Exclude storage/vendor/node_modules to keep it clean
rsync -a --delete   --exclude='.git'   --exclude='storage'   --exclude='vendor'   --exclude='node_modules'   "$SRC_DIR/" "$REL_DIR/"

# Link shared env + storage
if [[ ! -f "$SHARED_DIR/.env" ]]; then
  echo "WARNING: $SHARED_DIR/.env missing. Create it before production deploys." >&2
fi
ln -sfn "$SHARED_DIR/.env" "$REL_DIR/.env" || true
rm -rf "$REL_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$REL_DIR/storage"

cd "$REL_DIR"

echo "==> Installing PHP deps"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Building frontend"
npm ci
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config/routes/views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Swapping current symlink"
ln -sfn "$REL_DIR" "$CURRENT_LINK"

echo "==> Restarting queue workers (Supervisor if present)"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl reread || true
  supervisorctl update || true
  supervisorctl restart qrrevenue-worker:* || true
fi

echo "==> Optional: reload PHP-FPM (if systemd unit exists)"
if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.3-fpm 2>/dev/null || true
  systemctl reload php8.2-fpm 2>/dev/null || true
  systemctl reload php8.1-fpm 2>/dev/null || true
fi

echo "==> Health check"
if command -v curl >/dev/null 2>&1; then
  curl -fsS "${APP_URL:-http://localhost}/health" || (echo "Health check failed" && exit 1)
fi

echo "==> Pruning old releases (keep $KEEP_RELEASES)"
ls -1dt "$RELEASES_DIR"/* 2>/dev/null | tail -n +$((KEEP_RELEASES+1)) | xargs -r rm -rf

echo "Release deploy complete: $REL_NAME"
