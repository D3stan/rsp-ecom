# syntax=docker/dockerfile:1

FROM serversideup/php:8.3-fpm-nginx-v4.5.1 AS base
USER root
RUN install-php-extensions bcmath exif
USER www-data


FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress


# Vite resolves `ziggy-js` from vendor/, so assets are built after composer.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build


FROM base AS production

# Config caching stays off by choice: app code no longer calls env() outside config/ (Stripe keys come from
# config('cashier.*')), but enabling it has not been verified end to end for this deployment yet.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_MEMORY_LIMIT=512M \
    PHP_UPLOAD_MAX_FILE_SIZE=100M \
    PHP_POST_MAX_SIZE=202M \
    NGINX_CLIENT_MAX_BODY_SIZE=202M \
    PHP_MAX_EXECUTION_TIME=120 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_OPTIMIZE=false \
    AUTORUN_LARAVEL_CONFIG_CACHE=false \
    AUTORUN_LARAVEL_ROUTE_CACHE=false \
    AUTORUN_LARAVEL_VIEW_CACHE=false \
    AUTORUN_LARAVEL_EVENT_CACHE=false

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev --no-interaction

COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
