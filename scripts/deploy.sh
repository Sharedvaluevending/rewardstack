#!/usr/bin/env bash
#
# Safe deploy helper (does nothing until you run it).
# Designed for a typical Laravel server with Nginx + PHP-FPM + Supervisor.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/rewardstack}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

cd "$APP_DIR"

echo "==> Installing PHP deps"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Building frontend"
$NPM_BIN ci
$NPM_BIN run build

echo "==> Running migrations"
$PHP_BIN artisan migrate --force

echo "==> Caching config/routes/views"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "==> Restarting queue workers (if Supervisor is installed)"
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

echo "Deploy complete."
