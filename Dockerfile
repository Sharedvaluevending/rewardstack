# syntax=docker/dockerfile:1

# --- Install PHP dependencies (must be before node_build for Ziggy) ---
FROM composer:2 AS composer_build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

# --- Build frontend assets ---
FROM node:20-alpine AS node_build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js postcss.config.js tailwind.config.js ./
# Laravel Vite plugin expects a few paths
COPY public ./public
# Ziggy (routes) is in vendor - need it for Vite build
COPY --from=composer_build /app/vendor ./vendor
RUN npm run build

# --- Runtime image ---
FROM php:8.3-fpm-bookworm

# System deps + PHP extensions
RUN apt-get update   && apt-get install -y --no-install-recommends     git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev     libonig-dev libxml2-dev libicu-dev   && docker-php-ext-configure gd --with-freetype --with-jpeg   && docker-php-ext-install -j$(nproc) pdo_mysql zip gd intl bcmath exif pcntl   && pecl install redis   && docker-php-ext-enable redis   && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy app source
COPY . .

# Bring in vendor + built assets
COPY --from=composer_build /app/vendor ./vendor
COPY --from=node_build /app/public/build ./public/build

# PHP ini overrides
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Entrypoint
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint   && chown -R www-data:www-data /var/www/html

USER www-data
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
