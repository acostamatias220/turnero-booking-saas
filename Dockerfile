# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------------------
# Turnero - production-like image (Nginx + PHP-FPM, non-root).
# Stage 1 installs PHP deps, stage 2 builds the frontend, stage 3 is the runtime.
# ---------------------------------------------------------------------------

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Ziggy's TS types live in the Composer package.
COPY --from=vendor /app/vendor/tightenco /app/vendor/tightenco
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx AS app
USER root
RUN install-php-extensions intl
USER www-data

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
