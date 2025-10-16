FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev libonig-dev libxml2-dev \
 && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
 && rm -rf /var/lib/apt/lists/*

RUN pecl install redis && docker-php-ext-enable redis
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/php.ini
WORKDIR /var/www/backend
CMD ["php-fpm"]
