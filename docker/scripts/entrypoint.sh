#!/usr/bin/env sh
set -eu

cd /var/www/html

# Ensure writable dirs
mkdir -p storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rw storage bootstrap/cache || true

# If vendor is missing (dev mounts), install dependencies
if [ ! -d vendor ]; then
  echo "vendor/ missing, installing composer deps..."
  composer install --no-interaction --prefer-dist
fi

# If public/build missing, build assets (dev only). In prod we bake assets.
if [ ! -d public/build ] && [ "${APP_ENV:-local}" != "production" ]; then
  if command -v npm >/dev/null 2>&1; then
    echo "public/build missing, building frontend assets..."
    npm ci || npm install
    npm run build
  fi
fi

php-fpm
