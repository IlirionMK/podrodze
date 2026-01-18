FROM node:20-alpine AS frontend
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock ./
COPY backend/ ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev ca-certificates curl gnupg \
 && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
 && rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis

RUN curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg \
 && curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list \
 && apt-get update && apt-get install -y caddy \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/backend

COPY --from=vendor /app/backend /var/www/backend
COPY --from=frontend /app/frontend/dist /var/www/backend/public

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile

COPY docker/web/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
 && mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data /var/www/backend \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 8081

CMD ["/entrypoint.sh"]
